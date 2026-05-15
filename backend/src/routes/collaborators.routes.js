import { Router } from 'express';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth);

// GET /collaborators
router.get('/', async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM collaborators ORDER BY last_name, first_name');
  res.json(rows);
});

// GET /collaborators/:id
router.get('/:id', async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM collaborators WHERE id = ?', [req.params.id]);
  if (!rows.length) return res.status(404).json({ message: 'Collaboratore non trovato' });
  res.json(rows[0]);
});

// POST /collaborators  (solo admin)
router.post('/', requireAdmin, async (req, res) => {
  const { first_name, last_name, email, phone, fiscal_code, notes } = req.body;
  const [result] = await pool.query(
    'INSERT INTO collaborators (first_name, last_name, email, phone, fiscal_code, notes) VALUES (?,?,?,?,?,?)',
    [first_name, last_name, email, phone, fiscal_code, notes]
  );
  res.status(201).json({ id: result.insertId });
});

// PUT /collaborators/:id  (solo admin)
router.put('/:id', requireAdmin, async (req, res) => {
  const { first_name, last_name, email, phone, fiscal_code, notes, is_active } = req.body;
  await pool.query(
    'UPDATE collaborators SET first_name=?, last_name=?, email=?, phone=?, fiscal_code=?, notes=?, is_active=? WHERE id=?',
    [first_name, last_name, email, phone, fiscal_code, notes, is_active, req.params.id]
  );
  res.json({ message: 'Aggiornato' });
});

// DELETE /collaborators/:id  (solo admin)
router.delete('/:id', requireAdmin, async (req, res) => {
  await pool.query('UPDATE collaborators SET is_active = 0 WHERE id = ?', [req.params.id]);
  res.json({ message: 'Disattivato' });
});

export default router;
