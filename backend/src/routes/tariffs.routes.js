import { Router } from 'express';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth);

router.get('/', async (_req, res) => {
  const [rows] = await pool.query('SELECT * FROM tariffs ORDER BY is_default DESC, name');
  res.json(rows);
});

router.get('/:id', async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM tariffs WHERE id = ?', [req.params.id]);
  if (!rows.length) return res.status(404).json({ message: 'Tariffa non trovata' });
  res.json(rows[0]);
});

router.post('/', requireAdmin, async (req, res) => {
  const { name, rate_type, hourly_rate, valid_from, valid_to, is_default, tax_inclusive, notes } = req.body;
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    if (is_default) {
      await conn.query('UPDATE tariffs SET is_default = 0');
    }
    const [result] = await conn.query(
      'INSERT INTO tariffs (name, rate_type, hourly_rate, valid_from, valid_to, is_default, tax_inclusive, notes) VALUES (?,?,?,?,?,?,?,?)',
      [name, rate_type ?? 'hourly', hourly_rate, valid_from, valid_to ?? null, is_default ?? false, tax_inclusive ?? false, notes]
    );
    await conn.commit();
    res.status(201).json({ id: result.insertId });
  } catch (e) {
    await conn.rollback();
    throw e;
  } finally {
    conn.release();
  }
});

router.put('/:id', requireAdmin, async (req, res) => {
  const { name, rate_type, hourly_rate, valid_from, valid_to, is_default, tax_inclusive, notes } = req.body;
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    if (is_default) {
      await conn.query('UPDATE tariffs SET is_default = 0 WHERE id != ?', [req.params.id]);
    }
    await conn.query(
      'UPDATE tariffs SET name=?, rate_type=?, hourly_rate=?, valid_from=?, valid_to=?, is_default=?, tax_inclusive=?, notes=? WHERE id=?',
      [name, rate_type ?? 'hourly', hourly_rate, valid_from, valid_to ?? null, is_default ?? false, tax_inclusive ?? false, notes, req.params.id]
    );
    await conn.commit();
    res.json({ message: 'Aggiornato' });
  } catch (e) {
    await conn.rollback();
    throw e;
  } finally {
    conn.release();
  }
});

router.delete('/:id', requireAdmin, async (req, res) => {
  await pool.query('DELETE FROM tariffs WHERE id = ?', [req.params.id]);
  res.json({ message: 'Eliminato' });
});

export default router;
