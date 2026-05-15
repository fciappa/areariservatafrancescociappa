import express from 'express';
import db from '../config/db.js';
import { requireAuth, requireAdmin } from '../middleware/auth.js';

const router = express.Router();
router.use(requireAuth);

// GET /projects - lista tutti i progetti (con nome cliente)
router.get('/', async (req, res) => {
  try {
    const [rows] = await db.query(`
      SELECT p.*, c.company_name, c.vat_number
      FROM projects p
      JOIN clients c ON c.id = p.client_id
      ORDER BY p.created_at DESC
    `);
    res.json(rows);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// GET /projects/:id - dettaglio progetto con assegnazioni tariffe
router.get('/:id', async (req, res) => {
  try {
    const [[project]] = await db.query(`
      SELECT p.*, c.company_name, c.vat_number
      FROM projects p
      JOIN clients c ON c.id = p.client_id
      WHERE p.id = ?
    `, [req.params.id]);

    if (!project) return res.status(404).json({ message: 'Progetto non trovato' });

    const [assignments] = await db.query(`
      SELECT ta.id, ta.tariff_id, ta.collaborator_id,
             t.name AS tariff_name, t.hourly_rate, t.tax_inclusive,
             co.first_name, co.last_name
      FROM tariff_assignments ta
      JOIN tariffs t ON t.id = ta.tariff_id
      LEFT JOIN collaborators co ON co.id = ta.collaborator_id
      WHERE ta.project_id = ?
      ORDER BY ta.collaborator_id IS NULL DESC, co.last_name
    `, [req.params.id]);

    res.json({ ...project, assignments });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// POST /projects - crea progetto
router.post('/', requireAdmin, async (req, res) => {
  const { client_id, name, description, status, start_date, end_date, notes } = req.body;
  if (!client_id || !name || !start_date) {
    return res.status(400).json({ message: 'client_id, name e start_date sono obbligatori' });
  }
  try {
    const [result] = await db.query(
      'INSERT INTO projects (client_id, name, description, status, start_date, end_date, notes) VALUES (?,?,?,?,?,?,?)',
      [client_id, name, description ?? null, status ?? 'active', start_date, end_date ?? null, notes ?? null]
    );
    const [[row]] = await db.query(
      'SELECT p.*, c.company_name FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
      [result.insertId]
    );
    res.status(201).json(row);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// PUT /projects/:id - aggiorna progetto
router.put('/:id', requireAdmin, async (req, res) => {
  const { client_id, name, description, status, start_date, end_date, notes, is_active } = req.body;
  try {
    await db.query(
      `UPDATE projects SET client_id=?, name=?, description=?, status=?, start_date=?,
       end_date=?, notes=?, is_active=? WHERE id=?`,
      [client_id, name, description ?? null, status ?? 'active', start_date,
       end_date ?? null, notes ?? null, is_active !== false, req.params.id]
    );
    const [[row]] = await db.query(
      'SELECT p.*, c.company_name FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
      [req.params.id]
    );
    res.json(row);
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// ── Tariff assignments ────────────────────────────────────

// POST /projects/:id/assignments - assegna una tariffa al progetto
router.post('/:id/assignments', requireAdmin, async (req, res) => {
  const { tariff_id, collaborator_id } = req.body;
  if (!tariff_id) return res.status(400).json({ message: 'tariff_id obbligatorio' });
  try {
    const [result] = await db.query(
      'INSERT INTO tariff_assignments (tariff_id, project_id, collaborator_id) VALUES (?,?,?)',
      [tariff_id, req.params.id, collaborator_id ?? null]
    );
    const [[row]] = await db.query(`
      SELECT ta.*, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive,
             co.first_name, co.last_name
      FROM tariff_assignments ta
      JOIN tariffs t ON t.id = ta.tariff_id
      LEFT JOIN collaborators co ON co.id = ta.collaborator_id
      WHERE ta.id = ?
    `, [result.insertId]);
    res.status(201).json(row);
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({ message: 'Assegnazione già esistente per questo progetto e collaboratore' });
    }
    res.status(500).json({ message: err.message });
  }
});

// DELETE /projects/assignments/:assignmentId - rimuovi assegnazione
router.delete('/assignments/:assignmentId', requireAdmin, async (req, res) => {
  try {
    await db.query('DELETE FROM tariff_assignments WHERE id = ?', [req.params.assignmentId]);
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

// GET /projects/tariff/resolve?project_id=X&collaborator_id=Y
// Risolve la tariffa corretta per un progetto + collaboratore
router.get('/tariff/resolve', async (req, res) => {
  const { project_id, collaborator_id } = req.query;
  if (!project_id) return res.status(400).json({ message: 'project_id obbligatorio' });
  try {
    // 1. Tariffa specifica per collaboratore
    if (collaborator_id) {
      const [[specific]] = await db.query(`
        SELECT t.* FROM tariffs t
        JOIN tariff_assignments ta ON ta.tariff_id = t.id
        WHERE ta.project_id = ? AND ta.collaborator_id = ?
        LIMIT 1
      `, [project_id, collaborator_id]);
      if (specific) return res.json(specific);
    }
    // 2. Tariffa generica del progetto (collaborator_id IS NULL)
    const [[generic]] = await db.query(`
      SELECT t.* FROM tariffs t
      JOIN tariff_assignments ta ON ta.tariff_id = t.id
      WHERE ta.project_id = ? AND ta.collaborator_id IS NULL
      LIMIT 1
    `, [project_id]);
    if (generic) return res.json(generic);

    // 3. Fallback: tariffa di default globale
    const [[defaultTariff]] = await db.query(
      'SELECT * FROM tariffs WHERE is_default = TRUE LIMIT 1'
    );
    if (defaultTariff) return res.json(defaultTariff);

    res.status(404).json({ message: 'Nessuna tariffa trovata' });
  } catch (err) {
    res.status(500).json({ message: err.message });
  }
});

export default router;
