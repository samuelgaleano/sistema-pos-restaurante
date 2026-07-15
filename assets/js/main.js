// ============================================================
// POS PRO — Main JavaScript
// ============================================================

// Clock
function updateClock() {
  const el = document.getElementById('topbarTime');
  if (el) {
    const now = new Date();
    el.textContent = now.toLocaleTimeString('es-CO', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
  }
}
setInterval(updateClock, 1000);
updateClock();

// Sidebar Toggle
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const main = document.getElementById('main-content');
  if (window.innerWidth <= 900) {
    sidebar.classList.toggle('open');
  } else {
    sidebar.classList.toggle('collapsed');
    main.classList.toggle('expanded');
  }
}

// Modals
function openModal(id) {
  document.getElementById(id).classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('show');
  document.body.style.overflow = '';
}
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.show').forEach(m => {
      m.classList.remove('show');
      document.body.style.overflow = '';
    });
  }
});

// Alert auto dismiss
setTimeout(() => {
  document.querySelectorAll('.alert-auto').forEach(a => a.remove());
}, 4000);

// Number formatting
function formatMoney(n) {
  return '$ ' + parseFloat(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
}

// Confirm dialog
function confirmAction(msg, cb) {
  if (confirm(msg)) cb();
}

// AJAX helper
async function apiCall(url, data = {}, method = 'POST') {
  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: method !== 'GET' ? JSON.stringify(data) : undefined
  });
  return res.json();
}

// Toast notification
function showToast(msg, type = 'success') {
  const colors = { success: '#2ecc71', danger: '#e74c3c', warning: '#f39c12', info: '#3498db' };
  const toast = document.createElement('div');
  toast.style.cssText = `
    position:fixed;bottom:24px;right:24px;z-index:9999;
    background:${colors[type]||colors.success};color:#fff;
    padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;
    box-shadow:0 8px 24px rgba(0,0,0,.35);
    animation:slideInToast .3s ease;max-width:320px;
  `;
  toast.textContent = msg;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

// Add keyframes
const style = document.createElement('style');
style.textContent = '@keyframes slideInToast{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}';
document.head.appendChild(style);
