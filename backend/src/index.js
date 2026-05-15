import dotenv from 'dotenv';
dotenv.config({ path: `.env.${process.env.NODE_ENV || 'development'}` });
import express from 'express';
import cors from 'cors';

import authRoutes          from './routes/auth.routes.js';
import collaboratorRoutes  from './routes/collaborators.routes.js';
import clientRoutes        from './routes/clients.routes.js';
import tariffRoutes        from './routes/tariffs.routes.js';
import projectRoutes       from './routes/projects.routes.js';
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
app.use('/api/projects',      projectRoutes);
app.use('/api/hours',         hoursRoutes);
app.use('/api/invoices',      invoiceRoutes);
app.use('/api/users',         userRoutes);

// Error handler
app.use((err, _req, res, _next) => {
  console.error(err);
  res.status(500).json({ message: 'Errore interno del server' });
});

app.listen(PORT, () => console.log(`✅  Backend in ascolto su http://localhost:${PORT}`));
