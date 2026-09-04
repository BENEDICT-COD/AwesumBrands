(function () {
  const keys = { users: 'awesum_users', session: 'awesum_session', cart: 'awesum_cart', quotes: 'awesum_quotes', messages: 'awesum_messages', orders: 'awesum_orders', products: 'awesum_products', adverts: 'awesum_adverts', stats: 'awesum_stats', projects: 'awesum_projects' };
  const read = (key, fallback) => { try { return JSON.parse(localStorage.getItem(key)) || fallback; } catch (_) { return fallback; } };
  const write = (key, value) => localStorage.setItem(key, JSON.stringify(value));
  const currentUser = () => read(keys.session, null);
  const money = value => 'R ' + Math.round(Number(value) || 0).toLocaleString('en-ZA');
  const getCart = () => read(keys.cart, []);
  const setCart = cart => { write(keys.cart, cart); updateCartBadges(); };
  const escapeHtml = value => String(value == null ? '' : value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
  const requireUser = () => { const user = currentUser(); if (!user) { window.location.href = 'login.html'; return null; } return user; };
  const enforceAccess = async () => {
    const required = document.body.dataset.authRequired;
    if (!required) return;
    try {
      const result = await api('session');
      if (!result.user || (required === 'admin' && !result.isAdmin)) throw new Error('Access denied');
      write(keys.session, result.user);
    } catch (_) {
      localStorage.removeItem(keys.session);
      window.location.href = required === 'admin' ? 'login.html' : 'login.html';
    }
  };
  const addToCart = product => { const cart = getCart(); const existing = cart.find(item => item.id === product.id || item.name === product.name); if (existing) existing.quantity += 1; else cart.push({ ...product, quantity: 1 }); setCart(cart); return cart; };
  const api = async (action, options = {}) => { const response = await fetch('api.php?action=' + encodeURIComponent(action), { credentials: 'same-origin', headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options }); const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.error || 'Request failed.'); return payload; };
  const paymentGatewayUrl = window.AWE_SUM_PAYMENT_GATEWAY_URL || '';
  const updateCartBadges = () => document.querySelectorAll('.cart-badge').forEach(badge => { badge.textContent = getCart().reduce((sum, item) => sum + item.quantity, 0); });
  const updateAuthControls = () => {
    const user = currentUser();
    document.querySelectorAll('.login-link').forEach(loginLink => {
      if (!user) return;
      loginLink.textContent = 'Hello, ' + user.name;
      loginLink.href = '#';
    });
    document.querySelectorAll('.nav-right > .btn-primary').forEach(signUpLink => {
      if (!user) return;
      const signOutButton = document.createElement('button');
      signOutButton.type = 'button';
      signOutButton.className = signUpLink.className;
      signOutButton.textContent = 'Sign Out';
      signOutButton.addEventListener('click', async () => {
        signOutButton.disabled = true;
        try { await api('logout', { method: 'POST' }); }
        catch (_) {}
        localStorage.removeItem(keys.session);
        window.location.href = 'index.html';
      });
      signUpLink.replaceWith(signOutButton);
    });
  };
  const showMessage = (text, type) => {
    let node = document.getElementById('appMessage');
    if (!node) { node = document.createElement('div'); node.id = 'appMessage'; node.style.cssText = 'position:fixed;top:22px;right:22px;z-index:9999;padding:14px 18px;border-radius:10px;background:#0B1B33;color:#fff;font:600 14px Inter, sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.2)'; document.body.appendChild(node); }
    node.style.background = type === 'error' ? '#b42318' : '#0B1B33'; node.textContent = text; node.hidden = false; clearTimeout(node._timer); node._timer = setTimeout(() => { node.hidden = true; }, 3000);
  };
  window.AweSum = { keys, read, write, currentUser, money, getCart, setCart, updateCartBadges, showMessage, escapeHtml, requireUser, addToCart, paymentGatewayUrl, api };
  document.addEventListener('DOMContentLoaded', () => {
    enforceAccess();
    updateCartBadges();
    updateAuthControls();
    const contactForm = document.getElementById('contactForm');
    if (contactForm) contactForm.addEventListener('submit', async event => {
      event.preventDefault();
      const data = new FormData(contactForm);
      try { await api('message', { method: 'POST', body: JSON.stringify({ name: data.get('name'), email: data.get('email'), text: data.get('message') }) }); contactForm.reset(); showMessage('Thanks for reaching out. Our team will respond shortly.'); }
      catch (error) { showMessage(error.message, 'error'); }
    });
  });
})();
