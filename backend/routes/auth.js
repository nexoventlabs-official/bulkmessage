const router = require('express').Router();
const c = require('../controllers/authController');
const { requireUser } = require('../middleware/auth');

router.post('/login', c.login);
router.get('/me', requireUser, c.me);

module.exports = router;
