/* Admin panel: quick actions (WebServices/Admin.asmx) and the 3D hat preview. */
var BOBLOX = window.BOBLOX || {};
window.BOBLOX = BOBLOX;

BOBLOX.Admin = {
  run: function (method, params, done) {
    BOBLOX.Ajax.call('Admin', method, params, function (r) {
      if (done) done(r); else window.location.reload();
    }, function (msg) { alert(msg); });
  },

  // ---- users ----
  ban: function (userId, name) {
    var reason = prompt('Ban ' + name + '? They will be logged out and cannot log in again.\n\nReason (shown to them, optional):', '');
    if (reason === null) return;
    this.run('BanUser', { userId: userId, reason: reason });
  },
  unban: function (userId) {
    this.run('UnbanUser', { userId: userId });
  },
  setAdmin: function (userId, on, name) {
    if (!confirm(on ? 'Make ' + name + ' an admin? Admins can use this whole panel.' : 'Remove admin rights from ' + name + '?')) return;
    this.run('SetAdmin', { userId: userId, admin: on });
  },
  setPassword: function (userId, name) {
    var pw = prompt('New password for ' + name + ' (at least 6 characters):', '');
    if (pw === null || pw === '') return;
    this.run('SetPassword', { userId: userId, password: pw }, function () { alert('The password for ' + name + ' was changed.'); });
  },
  deleteUser: function (userId, name) {
    var typed = prompt('This deletes ' + name + ' for good, together with everything they made.\n\nType their username to confirm:', '');
    if (typed === null) return;
    this.run('DeleteUser', { userId: userId, confirmName: typed });
  },

  // ---- catalog ----
  deleteItem: function (button, itemId) {
    if (!confirm('Delete this item? It is taken off everyone who owns or wears it.')) return;
    this.run('DeleteItem', { itemId: itemId });
  },
  restoreItem: function (button, itemId) {
    this.run('RestoreItem', { itemId: itemId });
  },

  // ---- badges ----
  awardBadge: function (badgeId) {
    var input = $get('Award_' + badgeId), name = input.value.replace(/^\s+|\s+$/g, '');
    if (!name) { input.focus(); return; }
    this.run('AwardBadge', { badgeId: badgeId, username: name });
  },
  revokeBadge: function (badgeId, userId) {
    if (!confirm('Take this badge away?')) return;
    this.run('RevokeBadge', { badgeId: badgeId, userId: userId }, function () {
      var el = $get('Holder_' + badgeId + '_' + userId);
      if (el) el.parentNode.removeChild(el);
    });
  },
  deleteBadge: function (badgeId) {
    if (!confirm('Delete this badge? Everyone who has it loses it.')) return;
    this.run('DeleteBadge', { badgeId: badgeId });
  },

  // ---- hat preview (same look as the catalog pictures) ----
  initHatPreview: function () {
    var holder = $get('HatPreview');
    if (!holder || !window.THREE || !BOBLOX.Avatar3D || !BOBLOX.Avatar3D.isSupported()) {
      if (holder) holder.innerHTML = '<div class="Avatar3DUnsupported">No 3D preview in this browser.</div>';
      return;
    }
    var r = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
    r.outputEncoding = THREE.sRGBEncoding;
    r.setPixelRatio(1);
    r.setSize(420, 420);
    r.setClearColor(0x000000, 0);
    r.domElement.style.width = '250px';
    r.domElement.style.height = '250px';
    holder.appendChild(r.domElement);
    this.hatRenderer = r;
    this.updateHatPreview();
  },

  updateHatPreview: function () {
    if (!this.hatRenderer) return;
    var mesh = $get('Mesh').value, color = $get('Color').value;
    var scene = new THREE.Scene();
    scene.add(new THREE.AmbientLight(0xffffff, 0.62));
    var sun = new THREE.DirectionalLight(0xffffff, 0.7);
    sun.position.set(-4, 8, 10);
    scene.add(sun);
    var hat = BOBLOX.Hats[mesh].build(THREE, color, 1.23);
    scene.add(hat);
    var box = new THREE.Box3().setFromObject(hat);
    var c = box.getCenter(new THREE.Vector3()), size = box.getSize(new THREE.Vector3());
    var cam = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
    var d = Math.max(size.x, size.y, size.z) * 2.4;
    cam.position.set(c.x - d * 0.42, c.y + d * 0.35, c.z + d * 0.85);
    cam.lookAt(c);
    this.hatRenderer.render(scene, cam);
  },

  // Sends the preview picture along with the form so the catalog shows the right colour.
  beforeItemSave: function (form) {
    if (this.hatRenderer) {
      this.updateHatPreview();
      $get('Thumbnail').value = this.hatRenderer.domElement.toDataURL('image/png');
    }
    return true;
  }
};
