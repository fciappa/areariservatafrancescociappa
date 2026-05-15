import { Router } from 'express';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth, requireAdmin);

router.get('/', async (_req, res) => {
  const [rows] = await pool.query('SELECT * FROM clients ORDER BY company_name');
  res.json(rows);
});

router.get('/:id', async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM clients WHERE id = ?', [req.params.id]);
  if (!rows.length) return res.status(404).json({ message: 'Cliente non trovato' });
  res.json(rows[0]);
});

router.post('/', async (req, res) => {
  const { company_name, vat_number, email, phone, address, city, postal_code, country, notes } = req.body;
  const [result] = await pool.query(
    'INSERT INTO clients (company_name, vat_number, email, phone, address, city, postal_code, country, notes) VALUES (?,?,?,?,?,?,?,?,?)',
    [company_name, vat_number, email, phone, address, city, postal_code, country ?? 'Italia', notes]
  );
  res.status(201).json({ id: result.insertId });
});

router.put('/:id', async (req, res) => {
  const { company_name, vat_number, email, phone, address, city, postal_code, country, notes, is_active } = req.body;
  await pool.query(
    'UPDATE clients SET company_name=?, vat_number=?, email=?, phone=?, address=?, city=?, postal_code=?, country=?, notes=?, is_active=? WHERE id=?',
    [company_name, vat_number, email, phone, address, city, postal_code, country, notes, is_active, req.params.id]
  );
  res.json({ message: 'Aggiornato' });
});

router.delete('/:id', async (req, res) => {
  await pool.query('UPDATE clients SET is_active = 0 WHERE id = ?', [req.params.id]);
  res.json({ message: 'Disattivato' });
});

export default router;
