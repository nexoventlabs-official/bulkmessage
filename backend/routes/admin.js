const router = require('express').Router();
const a = require('../controllers/adminController');
const { requireAdmin } = require('../middleware/auth');

// Public: login
router.post('/login', a.login);

// Everything below requires a valid admin JWT
router.use(requireAdmin);

router.get('/me', a.me);
router.get('/users', a.listUsers);
router.get('/users/:id', a.getUser);
router.get('/users/:id/stats', a.getUserStats);
router.post('/users', a.createUser);
router.patch('/users/:id', a.updateUser);
router.post('/users/:id/regenerate-token', a.regenerateVerifyToken);
router.delete('/users/:id', a.deleteUser);

module.exports = router;
