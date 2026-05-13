const router = require('express').Router();
const c = require('../controllers/webhookController');

// Per-user webhook: /api/webhook/:userId
router.get('/:userId', c.verify);
router.post('/:userId', c.receive);

module.exports = router;
