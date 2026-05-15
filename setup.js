/**
 * Setup script - Area Riservata Francesco Ciappa
 * Eseguire UNA SOLA VOLTA dalla root del progetto:
 *   node setup.js
 *
 * Crea tutta la struttura backend + frontend.
 */

import { mkdirSync, writeFileSync, existsSync } from 'fs';
import { join } from 'path';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = __dirname;

function mkdir(rel) {
  const p = join(root, rel);
  if (!existsSync(p)) mkdirSync(p, { recursive: true });
}

function write(rel, content) {
  const p = join(root, rel);
  writeFileSync(p, content, 'utf8');
  console.log('  ✓', rel);
}

console.log('\n🚀 Creazione struttura progetto...\n');

// ─────────────────────────────────────────────
//  BACKEND - cartelle
// ─────────────────────────────────────────────
mkdir('backend/src/config');
mkdir('backend/src/controllers');
mkdir('backend/src/middleware');
mkdir('backend/src/models');
mkdir('backend/src/routes');

// ─────────────────────────────────────────────
//  BACKEND - package.json
// ─────────────────────────────────────────────
write('backend/package.json', JSON.stringify({
  name: 'areariservata-backend',
  version: '1.0.0',
  private: true,
  type: 'module',
  main: 'src/index.js',
  scripts: {
    dev: 'node --watch src/index.js',
    start: 'node src/index.js'
  },
  dependencies: {
    bcrypt: '^5.1.1',
    cors: '^2.8.5',
    dotenv: '^16.4.5',
    express: '^4.19.2',
    jsonwebtoken: '^9.0.2',
    mysql2: '^3.9.7'
  }
}, null, 2));

// ─────────────────────────────────────────────
//  BACKEND - .env.example
// ─────────────────────────────────────────────
write('backend/.env.example', `# Copia in .env e compila i valori
PORT=3001

# MySQL
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=areariservata

# JWT
JWT_SECRET=CAMBIA_QUESTO_SEGRETO_LUNGO_E_CASUALE
JWT_EXPIRES_IN=15m
JWT_REFRESH_SECRET=CAMBIA_QUESTO_REFRESH_SEGRETO
JWT_REFRESH_EXPIRES_IN=7d

# CORS - URL del frontend
CORS_ORIGIN=http://localhost:5173
`);

// ─────────────────────────────────────────────
//  BACKEND - src/config/db.js
// ─────────────────────────────────────────────
write('backend/src/config/db.js', `import mysql from 'mysql2/promise';
import 'dotenv/config';

const pool = mysql.createPool({
  host:     process.env.DB_HOST,
  port:     Number(process.env.DB_PORT) || 3306,
  user:     process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  waitForConnections: true,
  connectionLimit:    10,
});

export default pool;
`);

// ─────────────────────────────────────────────
//  BACKEND - src/middleware/auth.js
// ─────────────────────────────────────────────
write('backend/src/middleware/auth.js', `import jwt from 'jsonwebtoken';

export function requireAuth(req, res, next) {
  const header = req.headers.authorization;
  if (!header || !header.startsWith('Bearer ')) {
    return res.status(401).json({ message: 'Token mancante' });
  }
  const token = header.slice(7);
  try {
    req.user = jwt.verify(token, process.env.JWT_SECRET);
    next();
  } catch {
    return res.status(401).json({ message: 'Token non valido o scaduto' });
  }
}

export function requireAdmin(req, res, next) {
  if (req.user?.role !== 'admin') {
    return res.status(403).json({ message: 'Accesso riservato agli amministratori' });
  }
  next();
}
`);

// ─────────────────────────────────────────────
//  BACKEND - src/controllers/auth.controller.js
// ─────────────────────────────────────────────
write('backend/src/controllers/auth.controller.js', `import bcrypt from 'bcrypt';
import jwt from 'jsonwebtoken';
import pool from '../config/db.js';

function signAccess(user) {
  return jwt.sign(
    { id: user.id, role: user.role, collaborator_id: user.collaborator_id },
    process.env.JWT_SECRET,
    { expiresIn: process.env.JWT_EXPIRES_IN }
  );
}

function signRefresh(userId) {
  return jwt.sign({ id: userId }, process.env.JWT_REFRESH_SECRET, {
    expiresIn: process.env.JWT_REFRESH_EXPIRES_IN,
  });
}

export async function login(req, res) {
  const { username, password } = req.body;
  if (!username || !password)
    return res.status(400).json({ message: 'Username e password obbligatori' });

  const [rows] = await pool.query(
    'SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1',
    [username]
  );
  const user = rows[0];
  if (!user) return res.status(401).json({ message: 'Credenziali non valide' });

  const valid = await bcrypt.compare(password, user.password_hash);
  if (!valid) return res.status(401).json({ message: 'Credenziali non valide' });

  const accessToken  = signAccess(user);
  const refreshToken = signRefresh(user.id);

  // Calcola scadenza refresh
  const decoded = jwt.decode(refreshToken);
  await pool.query(
    'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))',
    [user.id, refreshToken, decoded.exp]
  );

  return res.json({
    accessToken,
    refreshToken,
    user: { id: user.id, username: user.username, role: user.role },
  });
}

export async function refresh(req, res) {
  const { refreshToken } = req.body;
  if (!refreshToken) return res.status(400).json({ message: 'Refresh token mancante' });

  try {
    const payload = jwt.verify(refreshToken, process.env.JWT_REFRESH_SECRET);
    const [rows] = await pool.query(
      'SELECT * FROM refresh_tokens WHERE token = ? AND expires_at > NOW()',
      [refreshToken]
    );
    if (!rows.length) return res.status(401).json({ message: 'Refresh token non valido' });

    const [userRows] = await pool.query('SELECT * FROM users WHERE id = ?', [payload.id]);
    const user = userRows[0];
    if (!user || !user.is_active) return res.status(401).json({ message: 'Utente non attivo' });

    return res.json({ accessToken: signAccess(user) });
  } catch {
    return res.status(401).json({ message: 'Refresh token scaduto o non valido' });
  }
}

export async function logout(req, res) {
  const { refreshToken } = req.body;
  if (refreshToken) {
    await pool.query('DELETE FROM refresh_tokens WHERE token = ?', [refreshToken]);
  }
  return res.json({ message: 'Logout effettuato' });
}
`);

// ─────────────────────────────────────────────
//  BACKEND - src/routes/auth.routes.js
// ─────────────────────────────────────────────
write('backend/src/routes/auth.routes.js', `import { Router } from 'express';
import { login, refresh, logout } from '../controllers/auth.controller.js';

const router = Router();

router.post('/login',   login);
router.post('/refresh', refresh);
router.post('/logout',  logout);

export default router;
`);

// ─────────────────────────────────────────────
//  BACKEND - src/routes/collaborators.routes.js
// ─────────────────────────────────────────────
write('backend/src/routes/collaborators.routes.js', `import { Router } from 'express';
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
`);

// ─────────────────────────────────────────────
//  BACKEND - src/routes/clients.routes.js
// ─────────────────────────────────────────────
write('backend/src/routes/clients.routes.js', `import { Router } from 'express';
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
`);

// ─────────────────────────────────────────────
//  BACKEND - src/routes/tariffs.routes.js
// ─────────────────────────────────────────────
write('backend/src/routes/tariffs.routes.js', `import { Router } from 'express';
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
  const { name, hourly_rate, valid_from, valid_to, is_default, tax_inclusive, notes } = req.body;
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    if (is_default) {
      await conn.query('UPDATE tariffs SET is_default = 0');
    }
    const [result] = await conn.query(
      'INSERT INTO tariffs (name, hourly_rate, valid_from, valid_to, is_default, tax_inclusive, notes) VALUES (?,?,?,?,?,?,?)',
      [name, hourly_rate, valid_from, valid_to ?? null, is_default ?? false, tax_inclusive ?? false, notes]
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
  const { name, hourly_rate, valid_from, valid_to, is_default, tax_inclusive, notes } = req.body;
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    if (is_default) {
      await conn.query('UPDATE tariffs SET is_default = 0 WHERE id != ?', [req.params.id]);
    }
    await conn.query(
      'UPDATE tariffs SET name=?, hourly_rate=?, valid_from=?, valid_to=?, is_default=?, tax_inclusive=?, notes=? WHERE id=?',
      [name, hourly_rate, valid_from, valid_to ?? null, is_default ?? false, tax_inclusive ?? false, notes, req.params.id]
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
`);

// ─────────────────────────────────────────────
//  BACKEND - src/routes/hours.routes.js
// ─────────────────────────────────────────────
write('backend/src/routes/hours.routes.js', `import { Router } from 'express';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth);

// ── Ore collaboratori ──────────────────────────────────────

// GET /hours/collaborators  (admin vede tutto, collaboratore vede solo le sue)
router.get('/collaborators', async (req, res) => {
  if (req.user.role === 'admin') {
    const [rows] = await pool.query(\`
      SELECT ch.*, c.first_name, c.last_name, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive
      FROM collaborator_hours ch
      JOIN collaborators c ON c.id = ch.collaborator_id
      JOIN tariffs t ON t.id = ch.tariff_id
      ORDER BY ch.work_date DESC
    \`);
    return res.json(rows);
  }
  // collaboratore: solo le sue
  const [rows] = await pool.query(\`
    SELECT ch.*, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive
    FROM collaborator_hours ch
    JOIN tariffs t ON t.id = ch.tariff_id
    WHERE ch.collaborator_id = ?
    ORDER BY ch.work_date DESC
  \`, [req.user.collaborator_id]);
  res.json(rows);
});

router.post('/collaborators', requireAdmin, async (req, res) => {
  const { collaborator_id, tariff_id, work_date, hours, description } = req.body;
  const [result] = await pool.query(
    'INSERT INTO collaborator_hours (collaborator_id, tariff_id, work_date, hours, description) VALUES (?,?,?,?,?)',
    [collaborator_id, tariff_id, work_date, hours, description]
  );
  res.status(201).json({ id: result.insertId });
});

router.put('/collaborators/:id', requireAdmin, async (req, res) => {
  const { collaborator_id, tariff_id, work_date, hours, description } = req.body;
  await pool.query(
    'UPDATE collaborator_hours SET collaborator_id=?, tariff_id=?, work_date=?, hours=?, description=? WHERE id=?',
    [collaborator_id, tariff_id, work_date, hours, description, req.params.id]
  );
  res.json({ message: 'Aggiornato' });
});

router.delete('/collaborators/:id', requireAdmin, async (req, res) => {
  await pool.query('DELETE FROM collaborator_hours WHERE id = ?', [req.params.id]);
  res.json({ message: 'Eliminato' });
});

// ── Ore mie (lavorate per clienti) ────────────────────────

router.get('/my', requireAdmin, async (_req, res) => {
  const [rows] = await pool.query(\`
    SELECT mwh.*, cl.company_name, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive
    FROM my_work_hours mwh
    JOIN clients cl ON cl.id = mwh.client_id
    JOIN tariffs t  ON t.id  = mwh.tariff_id
    ORDER BY mwh.work_date DESC
  \`);
  res.json(rows);
});

router.post('/my', requireAdmin, async (req, res) => {
  const { client_id, tariff_id, work_date, hours, description } = req.body;
  const [result] = await pool.query(
    'INSERT INTO my_work_hours (client_id, tariff_id, work_date, hours, description) VALUES (?,?,?,?,?)',
    [client_id, tariff_id, work_date, hours, description]
  );
  res.status(201).json({ id: result.insertId });
});

router.put('/my/:id', requireAdmin, async (req, res) => {
  const { client_id, tariff_id, work_date, hours, description } = req.body;
  await pool.query(
    'UPDATE my_work_hours SET client_id=?, tariff_id=?, work_date=?, hours=?, description=? WHERE id=?',
    [client_id, tariff_id, work_date, hours, description, req.params.id]
  );
  res.json({ message: 'Aggiornato' });
});

router.delete('/my/:id', requireAdmin, async (req, res) => {
  await pool.query('DELETE FROM my_work_hours WHERE id = ?', [req.params.id]);
  res.json({ message: 'Eliminato' });
});

export default router;
`);

// ─────────────────────────────────────────────
//  BACKEND - src/routes/invoices.routes.js
// ─────────────────────────────────────────────
write('backend/src/routes/invoices.routes.js', `import { Router } from 'express';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import pool from '../config/db.js';

const router = Router();
router.use(requireAuth, requireAdmin);

// GET /invoices?year=2024&month=5
router.get('/', async (req, res) => {
  let sql = \`
    SELECT i.*, c.company_name
    FROM invoices i
    JOIN clients c ON c.id = i.client_id
  \`;
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
  const [rows] = await pool.query(\`
    SELECT
      YEAR(invoice_date)  AS year,
      MONTH(invoice_date) AS month,
      COUNT(*)            AS count,
      SUM(total)          AS total_invoiced
    FROM invoices
    WHERE status != 'draft'
    GROUP BY YEAR(invoice_date), MONTH(invoice_date)
    ORDER BY year DESC, month DESC
  \`);
  res.json(rows);
});

export default router;
`);

// ─────────────────────────────────────────────
//  BACKEND - src/routes/users.routes.js
// ─────────────────────────────────────────────
write('backend/src/routes/users.routes.js', `import { Router } from 'express';
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
`);

// ─────────────────────────────────────────────
//  BACKEND - src/index.js
// ─────────────────────────────────────────────
write('backend/src/index.js', `import 'dotenv/config';
import express from 'express';
import cors from 'cors';

import authRoutes          from './routes/auth.routes.js';
import collaboratorRoutes  from './routes/collaborators.routes.js';
import clientRoutes        from './routes/clients.routes.js';
import tariffRoutes        from './routes/tariffs.routes.js';
import hoursRoutes         from './routes/hours.routes.js';
import invoiceRoutes       from './routes/invoices.routes.js';
import userRoutes          from './routes/users.routes.js';

const app  = express();
const PORT = process.env.PORT || 3001;

app.use(cors({ origin: process.env.CORS_ORIGIN }));
app.use(express.json());

// Routes
app.use('/api/auth',          authRoutes);
app.use('/api/collaborators', collaboratorRoutes);
app.use('/api/clients',       clientRoutes);
app.use('/api/tariffs',       tariffRoutes);
app.use('/api/hours',         hoursRoutes);
app.use('/api/invoices',      invoiceRoutes);
app.use('/api/users',         userRoutes);

// Error handler
app.use((err, _req, res, _next) => {
  console.error(err);
  res.status(500).json({ message: 'Errore interno del server' });
});

app.listen(PORT, () => console.log(\`✅  Backend in ascolto su http://localhost:\${PORT}\`));
`);

// ─────────────────────────────────────────────
//  FRONTEND - cartelle
// ─────────────────────────────────────────────
mkdir('frontend/src/assets');
mkdir('frontend/src/components');
mkdir('frontend/src/layouts');
mkdir('frontend/src/pages');
mkdir('frontend/src/router');
mkdir('frontend/src/stores');
mkdir('frontend/src/services');

// ─────────────────────────────────────────────
//  FRONTEND - package.json
// ─────────────────────────────────────────────
write('frontend/package.json', JSON.stringify({
  name: 'areariservata-frontend',
  version: '1.0.0',
  private: true,
  type: 'module',
  scripts: {
    dev:     'vite',
    build:   'vite build',
    preview: 'vite preview'
  },
  dependencies: {
    axios:        '^1.7.2',
    pinia:        '^2.1.7',
    'vue':         '^3.4.27',
    'vue-router':  '^4.3.3'
  },
  devDependencies: {
    '@vitejs/plugin-vue': '^5.0.5',
    vite:                 '^5.2.11'
  }
}, null, 2));

// ─────────────────────────────────────────────
//  FRONTEND - vite.config.js
// ─────────────────────────────────────────────
write('frontend/vite.config.js', `import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:3001',
        changeOrigin: true,
      },
    },
  },
});
`);

// ─────────────────────────────────────────────
//  FRONTEND - index.html
// ─────────────────────────────────────────────
write('frontend/index.html', `<!DOCTYPE html>
<html lang="it">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Area Riservata</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
`);

// ─────────────────────────────────────────────
//  FRONTEND - src/main.js
// ─────────────────────────────────────────────
write('frontend/src/main.js', `import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router/index.js';

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');
`);

// ─────────────────────────────────────────────
//  FRONTEND - src/App.vue
// ─────────────────────────────────────────────
write('frontend/src/App.vue', `<template>
  <RouterView />
</template>
`);

// ─────────────────────────────────────────────
//  FRONTEND - src/services/api.js
// ─────────────────────────────────────────────
write('frontend/src/services/api.js', `import axios from 'axios';

const api = axios.create({ baseURL: '/api' });

// Allega il token ad ogni richiesta
api.interceptors.request.use(config => {
  const token = localStorage.getItem('accessToken');
  if (token) config.headers.Authorization = \`Bearer \${token}\`;
  return config;
});

// Refresh automatico su 401
api.interceptors.response.use(
  res => res,
  async err => {
    const original = err.config;
    if (err.response?.status === 401 && !original._retry) {
      original._retry = true;
      const refreshToken = localStorage.getItem('refreshToken');
      if (!refreshToken) {
        window.location.href = '/login';
        return Promise.reject(err);
      }
      try {
        const { data } = await axios.post('/api/auth/refresh', { refreshToken });
        localStorage.setItem('accessToken', data.accessToken);
        original.headers.Authorization = \`Bearer \${data.accessToken}\`;
        return api(original);
      } catch {
        localStorage.clear();
        window.location.href = '/login';
      }
    }
    return Promise.reject(err);
  }
);

export default api;
`);

// ─────────────────────────────────────────────
//  FRONTEND - src/stores/auth.js
// ─────────────────────────────────────────────
write('frontend/src/stores/auth.js', `import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api.js';

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(JSON.parse(localStorage.getItem('user') || 'null'));
  const token = ref(localStorage.getItem('accessToken') || null);

  const isLoggedIn  = computed(() => !!token.value);
  const isAdmin     = computed(() => user.value?.role === 'admin');

  async function login(username, password) {
    const { data } = await api.post('/auth/login', { username, password });
    token.value = data.accessToken;
    user.value  = data.user;
    localStorage.setItem('accessToken',  data.accessToken);
    localStorage.setItem('refreshToken', data.refreshToken);
    localStorage.setItem('user',         JSON.stringify(data.user));
  }

  async function logout() {
    const refreshToken = localStorage.getItem('refreshToken');
    await api.post('/auth/logout', { refreshToken }).catch(() => {});
    token.value = null;
    user.value  = null;
    localStorage.clear();
  }

  return { user, token, isLoggedIn, isAdmin, login, logout };
});
`);

// ─────────────────────────────────────────────
//  FRONTEND - src/router/index.js
// ─────────────────────────────────────────────
write('frontend/src/router/index.js', `import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const routes = [
  { path: '/login', component: () => import('../pages/LoginPage.vue'), meta: { public: true } },

  {
    path: '/',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      { path: '',              component: () => import('../pages/DashboardPage.vue') },
      { path: 'collaboratori', component: () => import('../pages/CollaboratorsPage.vue'), meta: { adminOnly: true } },
      { path: 'clienti',       component: () => import('../pages/ClientsPage.vue'),       meta: { adminOnly: true } },
      { path: 'tariffario',    component: () => import('../pages/TariffsPage.vue'),        meta: { adminOnly: true } },
      { path: 'ore-mie',       component: () => import('../pages/MyHoursPage.vue'),        meta: { adminOnly: true } },
      { path: 'ore-collaboratori', component: () => import('../pages/CollabHoursPage.vue'),meta: { adminOnly: true } },
      { path: 'fatture',       component: () => import('../pages/InvoicesPage.vue'),       meta: { adminOnly: true } },
      { path: 'fatture/nuova', component: () => import('../pages/NewInvoicePage.vue'),     meta: { adminOnly: true } },
      { path: 'utenti',        component: () => import('../pages/UsersPage.vue'),          meta: { adminOnly: true } },
      { path: 'riepilogo',     component: () => import('../pages/SummaryPage.vue') },
    ],
  },

  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (!to.meta.public && !auth.isLoggedIn) return '/login';
  if (to.meta.adminOnly && !auth.isAdmin)  return '/';
});

export default router;
`);

// ─────────────────────────────────────────────
//  FRONTEND - layout e pagine placeholder
// ─────────────────────────────────────────────
write('frontend/src/layouts/DashboardLayout.vue', `<template>
  <div class="app-layout">
    <nav class="sidebar">
      <h1>Area Riservata</h1>
      <RouterLink to="/">Dashboard</RouterLink>
      <RouterLink to="/riepilogo">Riepilogo mensile</RouterLink>
      <template v-if="auth.isAdmin">
        <RouterLink to="/collaboratori">Collaboratori</RouterLink>
        <RouterLink to="/clienti">Clienti</RouterLink>
        <RouterLink to="/tariffario">Tariffario</RouterLink>
        <RouterLink to="/ore-mie">Le mie ore</RouterLink>
        <RouterLink to="/ore-collaboratori">Ore collaboratori</RouterLink>
        <RouterLink to="/fatture">Fatture</RouterLink>
        <RouterLink to="/utenti">Utenti</RouterLink>
      </template>
      <button @click="auth.logout">Esci</button>
    </nav>
    <main class="content">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { useAuthStore } from '../stores/auth.js';
const auth = useAuthStore();
</script>
`);

const pages = [
  ['LoginPage',        'Login'],
  ['DashboardPage',    'Dashboard'],
  ['CollaboratorsPage','Collaboratori'],
  ['ClientsPage',      'Clienti'],
  ['TariffsPage',      'Tariffario'],
  ['MyHoursPage',      'Le mie ore'],
  ['CollabHoursPage',  'Ore collaboratori'],
  ['InvoicesPage',     'Fatture'],
  ['NewInvoicePage',   'Nuova fattura'],
  ['UsersPage',        'Utenti'],
  ['SummaryPage',      'Riepilogo mensile'],
];

for (const [name, title] of pages) {
  write(`frontend/src/pages/${name}.vue`, `<template>
  <div>
    <h2>${title}</h2>
    <!-- TODO: implementare ${name} -->
  </div>
</template>
`);
}

console.log('\n✅  Struttura progetto creata con successo!\n');
console.log('Prossimi passi:');
console.log('  1. cd backend && cp .env.example .env  (e compila i valori)');
console.log('  2. mysql -u root -p < database.sql');
console.log('  3. npm install  (dalla root)');
console.log('  4. npm run dev  (dalla root)\n');
