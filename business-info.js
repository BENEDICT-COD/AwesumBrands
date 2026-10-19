// Edit the details below ONCE. The legal pages and the footer of every page use them.
(function () {
  var business = {
    tradingName: 'AweSum Brands',
    legalName: '',            // e.g. 'AweSum Brands (Pty) Ltd'. Leave empty if you trade in your own name.
    email: 'info@awesumbrands.co.za',
    phone: '0657350139',
    address: '447 anton lembede street',
    registrationNumber: '',   // company registration number, if you have one
    vatNumber: ''             // VAT number. Leave empty if you are not VAT registered.
  };
  window.AweSumBusiness = business;

  function fill() {
    var name = business.legalName || business.tradingName;
    document.querySelectorAll('[data-biz]').forEach(function (el) {
      var key = el.getAttribute('data-biz');
      var value = key === 'name' ? name : (business[key] || '');
      el.textContent = value;
      if (key === 'email' && el.tagName === 'A') el.setAttribute('href', 'mailto:' + value);
    });
    var copyright = document.getElementById('bizCopyright');
    if (copyright) copyright.textContent = '\u00a9 ' + new Date().getFullYear() + ' ' + name + '. All rights reserved.';
    var registration = document.getElementById('bizRegistration');
    if (registration) {
      var parts = [];
      if (business.registrationNumber) parts.push('Reg. No. ' + business.registrationNumber);
      if (business.vatNumber) parts.push('VAT No. ' + business.vatNumber);
      registration.textContent = parts.join(' \u00b7 ');
    }
  }
  fetch('api.php?action=site-content', { credentials: 'same-origin' })
    .then(function (response) {
      if (!response.ok) throw new Error('Could not load saved business details.');
      return response.json();
    })
    .then(function (result) {
      if (!result.content) return;
      Object.keys(business).forEach(function (key) {
        if (Object.prototype.hasOwnProperty.call(result.content, key)) business[key] = result.content[key];
      });
      fill();
    })
    .catch(function (error) { console.error(error); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fill); else fill();
})();
