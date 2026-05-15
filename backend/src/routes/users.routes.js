import { Router } from 'express';
import bcrypt from 'bcrypt';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth, requireAdmin);

router.get('/', async (_req, res) => {
  const [rows] = await pool.query(
    'SELECT id, username, email, role, collaborator_id, is_active, created_at FROM users ORDER BY username'
  );
  res.json(rows);
});

router.post('/', async (req, res) => {
  const { username, email, password, role, collaborator_id } = req.body;
  const hash = await bcrypt.hash(password, 10);
  const [result] = await pool.query(
    'INSERT INTO users (username, email, password_hash, role, collaborator_id) VALUES (?,?,?,?,?)',
    [username, email, hash, role ?? 'collaborator', collaborator_id ?? null]
  );
  res.status(201).json({ id: result.insertId });
});

router.put('/:id/password', async (req, res) => {
  const { password } = req.body;
  const hash = await bcrypt.hash(password, 10);
  await pool.query('UPDATE users SET password_hash = ? WHERE id = ?', [hash, req.params.id]);
  res.json({ message: 'Password aggiornata' });
});

router.put('/:id/toggle', async (req, res) => {
  await pool.query('UPDATE users SET is_active = NOT is_active WHERE id = ?', [req.params.id]);
  res.json({ message: 'Stato aggiornato' });
});

export default router;
