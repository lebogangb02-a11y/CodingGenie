function validatePasswordForm() {
  const form = document.getElementById('passwordForm');
  if (!form) return true;
  const current = form.querySelector('[name="current_password"]').value.trim();
  const pwd = form.querySelector('[name="new_password"]').value;
  const confirm = form.querySelector('[name="confirm_password"]').value;
  const errs = [];
  if (!current) errs.push('Enter current password.');
  if (pwd.length < 10) errs.push('Password must be at least 10 characters.');
  if (!/[A-Z]/.test(pwd)) errs.push('Include at least one uppercase letter.');
  if (!/[a-z]/.test(pwd)) errs.push('Include at least one lowercase letter.');
  if (!/[0-9]/.test(pwd)) errs.push('Include at least one number.');
  if (pwd !== confirm) errs.push('New password and confirmation do not match.');
  if (errs.length) { alert(errs.join('\n')); return false; }
  return true;
}

function validateImageUpload() {
  const input = document.querySelector('input[name="profile_image"]');
  if (!input || !input.files.length) return true;
  const file = input.files[0];
  if (file.size > 2 * 1024 * 1024) { alert('Image exceeds 2MB limit.'); return false; }
  const mime = file.type;
  if (!["image/jpeg","image/png"].includes(mime)) { alert('Only JPG/PNG allowed.'); return false; }
  return true;
}

// Bootstrap modal validation feedback
(function () {
  const form = document.getElementById('editProfileForm');
  if (!form) return;
  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add('was-validated');
  }, false);
})();