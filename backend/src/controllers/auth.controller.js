import bcrypt from 'bcrypt';
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
