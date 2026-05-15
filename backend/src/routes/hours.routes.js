import { Router } from 'express';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth);

// ── Ore collaboratori ──────────────────────────────────────

// GET /hours/collaborators  (admin vede tutto, collaboratore vede solo le sue)
router.get('/collaborators', async (req, res) => {
  if (req.user.role === 'admin') {
    const [rows] = await pool.query(`
      SELECT ch.*, c.first_name, c.last_name, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive
      FROM collaborator_hours ch
      JOIN collaborators c ON c.id = ch.collaborator_id
      JOIN tariffs t ON t.id = ch.tariff_id
      ORDER BY ch.work_date DESC
    `);
    return res.json(rows);
  }
  // collaboratore: solo le sue
  const [rows] = await pool.query(`
    SELECT ch.*, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive
    FROM collaborator_hours ch
    JOIN tariffs t ON t.id = ch.tariff_id
    WHERE ch.collaborator_id = ?
    ORDER BY ch.work_date DESC
  `, [req.user.collaborator_id]);
  res.json(rows);
});

router.post('/collaborators', requireAdmin, async (req, res) => {
  const { collaborator_id, project_id, tariff_id, work_date, hours, description } = req.body;
  const [result] = await pool.query(
    'INSERT INTO collaborator_hours (collaborator_id, project_id, tariff_id, work_date, hours, description) VALUES (?,?,?,?,?,?)',
    [collaborator_id, project_id ?? null, tariff_id, work_date, hours, description]
  );
  res.status(201).json({ id: result.insertId });
});

router.put('/collaborators/:id', requireAdmin, async (req, res) => {
  const { collaborator_id, project_id, tariff_id, work_date, hours, description } = req.body;
  await pool.query(
    'UPDATE collaborator_hours SET collaborator_id=?, project_id=?, tariff_id=?, work_date=?, hours=?, description=? WHERE id=?',
    [collaborator_id, project_id ?? null, tariff_id, work_date, hours, description, req.params.id]
  );
  res.json({ message: 'Aggiornato' });
});

router.delete('/collaborators/:id', requireAdmin, async (req, res) => {
  await pool.query('DELETE FROM collaborator_hours WHERE id = ?', [req.params.id]);
  res.json({ message: 'Eliminato' });
});

// ── Ore mie (lavorate per clienti) ────────────────────────

router.get('/my', requireAdmin, async (_req, res) => {
  const [rows] = await pool.query(`
    SELECT mwh.*, cl.company_name, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive
    FROM my_work_hours mwh
    JOIN clients cl ON cl.id = mwh.client_id
    JOIN tariffs t  ON t.id  = mwh.tariff_id
    ORDER BY mwh.work_date DESC
  `);
  res.json(rows);
});

router.post('/my', requireAdmin, async (req, res) => {
  const { client_id, project_id, tariff_id, work_date, hours, description } = req.body;
  const [result] = await pool.query(
    'INSERT INTO my_work_hours (client_id, project_id, tariff_id, work_date, hours, description) VALUES (?,?,?,?,?,?)',
    [client_id, project_id ?? null, tariff_id, work_date, hours, description]
  );
  res.status(201).json({ id: result.insertId });
});

router.put('/my/:id', requireAdmin, async (req, res) => {
  const { client_id, project_id, tariff_id, work_date, hours, description } = req.body;
  await pool.query(
    'UPDATE my_work_hours SET client_id=?, project_id=?, tariff_id=?, work_date=?, hours=?, description=? WHERE id=?',
    [client_id, project_id ?? null, tariff_id, work_date, hours, description, req.params.id]
  );
  res.json({ message: 'Aggiornato' });
});

router.delete('/my/:id', requireAdmin, async (req, res) => {
  await pool.query('DELETE FROM my_work_hours WHERE id = ?', [req.params.id]);
  res.json({ message: 'Eliminato' });
});

export default router;
