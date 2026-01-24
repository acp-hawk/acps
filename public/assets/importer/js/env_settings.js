
// Loads whitelisted .env values for the settings tab
function loadEnvSettings() {
  fetch('/admin/update_env.php', {method:'GET'})
    .then(resp => resp.json())
    .then(data => {
      if (data) {
        if (document.getElementById('env_name')) document.getElementById('env_name').value = data.SYSTEM_NAME || '';
        if (document.getElementById('env_email')) document.getElementById('env_email').value = data.ADMIN_EMAIL || '';
        if (document.getElementById('env_password')) document.getElementById('env_password').value = data.ADMIN_PASSWORD || '';
      }
    });
}

document.addEventListener('DOMContentLoaded', function() {
  loadEnvSettings();
  var form = document.getElementById('envSettingsForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      var name = document.getElementById('env_name').value;
      var email = document.getElementById('env_email').value;
      var password = document.getElementById('env_password').value;
      fetch('/admin/update_env.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          env_name: name,
          env_email: email,
          env_password: password
        }).toString()
      })
      .then(resp => resp.json())
      .then(data => {
        const status = document.getElementById('envSaveStatus');
        if (data.status === 'success') {
          status.textContent = 'Settings saved.';
          status.style.color = 'green';
        } else {
          status.textContent = data.message || 'Error saving settings.';
          status.style.color = 'red';
        }
      });
    });
  }
});
