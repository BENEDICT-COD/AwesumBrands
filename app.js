(function () {
  const keys = { users: 'awesum_users', session: 'awesum_session', cart: 'awesum_cart', quotes: 'awesum_quotes', messages: 'awesum_messages', orders: 'awesum_orders', products: 'awesum_products', adverts: 'awesum_adverts', stats: 'awesum_stats', projects: 'awesum_projects' };
  const read = (key, fallback) => { try { return JSON.parse(localStorage.getItem(key)) || fallback; } catch (_) { return fallback; } };
  const write = (key, value) => localStorage.setItem(key, JSON.stringify(value));
  const currentUser = () => read(keys.session, null);
  const money = value => { const amount = Number(value) || 0; const hasCents = Math.round(amount * 100) % 100 !== 0; return 'R ' + amount.toLocaleString('en-ZA', { minimumFractionDigits: hasCents ? 2 : 0, maximumFractionDigits: hasCents ? 2 : 0 }); };
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
  // Keeps the greeting in step with the real server session on pages that do not require a login.
  const syncSession = async () => {
    if (document.body.dataset.authRequired) return;
    const stored = currentUser();
    try {
      const result = await api('session');
      if (result.user) { write(keys.session, result.user); if (!stored) updateAuthControls(); }
      else if (stored) { localStorage.removeItem(keys.session); window.location.reload(); }
    } catch (_) {}
  };
  const addToCart = product => { const cart = getCart(); const existing = cart.find(item => item.id === product.id || item.name === product.name); if (existing) existing.quantity += 1; else cart.push({ ...product, quantity: 1 }); setCart(cart); return cart; };
  // Puts an accepted custom quote in the cart at the price the admin set. A quote can be in the cart only once.
  const addQuoteToCart = quote => {
    const cart = getCart().filter(item => item.quoteId !== quote.id);
    cart.push({ id: 'quote:' + quote.id, quoteId: quote.id, name: 'Custom quote ' + quote.id + ' (' + quote.category + ', qty ' + quote.quantity + ')', price: Number(quote.quotedPrice) || 0, quantity: 1, category: 'Custom quote', image: quote.draftImage || '' });
    setCart(cart);
    return cart;
  };
  const api = async (action, options = {}) => { const response = await fetch('api.php?action=' + encodeURIComponent(action), { credentials: 'same-origin', headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options }); const payload = await response.json().catch(() => null); if (response.status === 401 && !['login', 'change-password'].includes(action)) { localStorage.removeItem(keys.session); setTimeout(() => { if (!/login\.html$/.test(window.location.pathname)) window.location.href = 'login.html'; }, 1500); throw new Error('Your session has expired. Please sign in again.'); } if (!response.ok) throw new Error((payload && payload.error) || 'Request failed.'); if (payload === null) throw new Error('The server sent an unexpected response. Please try again.'); return payload; };
  const paymentGatewayUrl = window.AWE_SUM_PAYMENT_GATEWAY_URL || '';
  const updateCartBadges = () => document.querySelectorAll('.cart-badge').forEach(badge => { badge.textContent = getCart().reduce((sum, item) => sum + item.quantity, 0); });
  const updateAuthControls = () => {
    const user = currentUser();
    document.querySelectorAll('.login-link').forEach(loginLink => {
      if (!user) return;
      loginLink.textContent = 'Hello, ' + user.name;
      loginLink.href = 'change-password.html';
      loginLink.title = 'Change password';
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
  // Shrinks a chosen image (max 1600px, JPEG) before upload so requests stay small and pages load fast.
  const imageToDataUrl = (file, maxSide = 1600, quality = 0.85) => new Promise((resolve, reject) => {
    if (!file || !String(file.type).startsWith('image/')) { reject(new Error('Please choose an image file.')); return; }
    const readOriginal = () => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result || '')); reader.onerror = () => reject(new Error('Could not read the image.')); reader.readAsDataURL(file); };
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      try {
        const scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
        if (scale === 1 && file.size <= 600 * 1024 && /^image\/(png|jpeg|webp)$/.test(file.type)) { readOriginal(); return; }
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
        const context = canvas.getContext('2d');
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(img, 0, 0, canvas.width, canvas.height);
        resolve(canvas.toDataURL('image/jpeg', quality));
      } catch (error) { readOriginal(); }
    };
    img.onerror = () => { URL.revokeObjectURL(url); readOriginal(); };
    img.src = url;
  });
  // Sends the customer to PayFast's secure payment page for one of their orders.
  const payWithPayFast = async reference => {
    const data = await api('payfast-checkout', { method: 'POST', body: JSON.stringify({ reference }) });
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = data.url;
    Object.entries(data.fields).forEach(([name, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  };
  window.AweSum = { keys, read, write, currentUser, money, getCart, setCart, updateCartBadges, showMessage, escapeHtml, requireUser, addToCart, paymentGatewayUrl, api, imageToDataUrl, payWithPayFast, addQuoteToCart };
  document.addEventListener('DOMContentLoaded', () => {
    enforceAccess();
    syncSession();
    updateCartBadges();
    updateAuthControls();
    const contactForm = document.getElementById('contactForm');
    if (contactForm) contactForm.addEventListener('submit', async event => {
      event.preventDefault();
      const data = new FormData(contactForm);
      try { await api('message', { method: 'POST', body: JSON.stringify({ name: data.get('name'), email: data.get('email'), text: data.get('message'), website: data.get('website') }) }); contactForm.reset(); showMessage('Thanks for reaching out. Our team will respond shortly.'); }
      catch (error) { showMessage(error.message, 'error'); }
    });
  });
})();