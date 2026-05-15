import { Router } from 'express';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth, requireAdmin);

// GET /invoices?year=2024&month=5
router.get('/', async (req, res) => {
  let sql = `
    SELECT i.*, c.company_name
    FROM invoices i
    JOIN clients c ON c.id = i.client_id
  `;
  const params = [];
  if (req.query.year && req.query.month) {
    sql += ' WHERE YEAR(i.invoice_date) = ? AND MONTH(i.invoice_date) = ?';
    params.push(req.query.year, req.query.month);
  } else if (req.query.year) {
    sql += ' WHERE YEAR(i.invoice_date) = ?';
    params.push(req.query.year);
  }
  sql += ' ORDER BY i.invoice_date DESC';
  const [rows] = await pool.query(sql, params);
  res.json(rows);
});

router.get('/:id', async (req, res) => {
  const [[invoice]] = await pool.query(
    'SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ?',
    [req.params.id]
  );
  if (!invoice) return res.status(404).json({ message: 'Fattura non trovata' });
  const [items] = await pool.query(
    'SELECT * FROM invoice_items WHERE invoice_id = ?',
    [req.params.id]
  );
  res.json({ ...invoice, items });
});

// Simula / calcola una fattura prima di salvarla
router.post('/simulate', async (req, res) => {
  const { items, stamp_duty = 2 } = req.body;
  // items: [{ hours, hourly_rate, tax_inclusive }]
  let subtotal = 0;
  let taxAmount = 0;
  const computed = items.map(item => {
    const gross = parseFloat(item.hourly_rate) * parseFloat(item.hours);
    let imponibile, tax;
    if (item.tax_inclusive) {
      imponibile = gross / 1.04;
      tax        = gross - imponibile;
    } else {
      imponibile = gross;
      tax        = gross * 0.04;
    }
    subtotal  += imponibile;
    taxAmount += tax;
    return { ...item, gross: +gross.toFixed(2), imponibile: +imponibile.toFixed(2), tax: +tax.toFixed(2) };
  });
  const total = subtotal + taxAmount + parseFloat(stamp_duty);
  res.json({
    items:      computed,
    subtotal:   +subtotal.toFixed(2),
    tax_amount: +taxAmount.toFixed(2),
    stamp_duty: +parseFloat(stamp_duty).toFixed(2),
    total:      +total.toFixed(2),
  });
});

// Salva fattura
router.post('/', async (req, res) => {
  const { invoice_number, client_id, invoice_date, stamp_duty = 2, subtotal, tax_amount, total, notes, items } = req.body;
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [result] = await conn.query(
      'INSERT INTO invoices (invoice_number, client_id, invoice_date, stamp_duty, subtotal, tax_amount, total, notes) VALUES (?,?,?,?,?,?,?,?)',
      [invoice_number, client_id, invoice_date, stamp_duty, subtotal, tax_amount, total, notes]
    );
    const invoiceId = result.insertId;
    for (const item of (items ?? [])) {
      await conn.query(
        'INSERT INTO invoice_items (invoice_id, work_hour_id, description, tariff_id, hours, hourly_rate, tax_inclusive, line_total) VALUES (?,?,?,?,?,?,?,?)',
        [invoiceId, item.work_hour_id ?? null, item.description, item.tariff_id, item.hours, item.hourly_rate, item.tax_inclusive, item.line_total]
      );
    }
    await conn.commit();
    res.status(201).json({ id: invoiceId });
  } catch (e) {
    await conn.rollback();
    throw e;
  } finally {
    conn.release();
  }
});

router.put('/:id/status', async (req, res) => {
  const { status } = req.body;
  await pool.query('UPDATE invoices SET status = ? WHERE id = ?', [status, req.params.id]);
  res.json({ message: 'Stato aggiornato' });
});

// Riepilogo mensile
router.get('/summary/monthly', async (req, res) => {
  const [rows] = await pool.query(`
    SELECT
      YEAR(invoice_date)  AS year,
      MONTH(invoice_date) AS month,
      COUNT(*)            AS count,
      SUM(total)          AS total_invoiced
    FROM invoices
    WHERE status != 'draft'
    GROUP BY YEAR(invoice_date), MONTH(invoice_date)
    ORDER BY year DESC, month DESC
  `);
  res.json(rows);
});

export default router;
