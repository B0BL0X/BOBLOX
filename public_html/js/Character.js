/*
 * The avatar changer (My/Character.aspx).
 * Every change is saved straight away over AJAX (WebServices/Character.asmx); afterwards the
 * figure is re-rendered in 3D and a fresh 420x420 thumbnail is uploaded for profiles.
 */
var BOBLOX = window.BOBLOX || {};
window.BOBLOX = BOBLOX;

BOBLOX.Character = {
  viewer: null,
  state: null,
  currentType: 8,
  currentPage: 1,
  pickingPart: null,
  thumbTimer: null,
  thumbVersion: 0,
  busy: 0,

  partLabels: {
    Head: 'Head', Torso: 'Torso', LeftArm: 'Left Arm', RightArm: 'Right Arm', LeftLeg: 'Left Leg', RightLeg: 'Right Leg'
  },

  init: function () {
    var self = this;
    this.viewer = new BOBLOX.Avatar3D($get('AvatarViewer'), { width: 300, height: 360 });
    if (!this.viewer.supported) {
      $get('AvatarViewer').style.display = 'none';
      $get('AvatarFallback').style.display = '';
      $get('AvatarStatus').innerHTML = 'Your browser cannot show 3D, so you see a simple picture instead.';
    }
    this.buildSwatches();
    this.applyState(BOBLOX.CharacterStartState, false);
    this.showWardrobe(BOBLOX.CharacterStartType || 8, 1);

    // Profiles show a rendered picture; make one if the stored one is only the simple fallback.
    if (this.viewer.supported && !BOBLOX.CharacterStartState.thumbRendered) {
      this.viewer.ready(function () { self.scheduleThumbnail(100); });
    }
  },

  setStatus: function (text, isError) {
    var el = $get('AvatarStatus');
    el.className = 'StatusMessage' + (isError ? ' Error' : '');
    el.innerHTML = text;
  },

  call: function (method, params, after) {
    var self = this;
    this.busy++;
    this.setStatus('Saving...');
    BOBLOX.Ajax.call('Character', method, params, function (state) {
      self.busy--;
      self.applyState(state, true);
      if (after) after();
    }, function (message) {
      self.busy--;
      self.setStatus(message.replace(/</g, '&lt;'), true);
    });
  },

  applyState: function (state, changed) {
    this.state = state;
    if (this.viewer.supported) {
      this.viewer.setAppearance(state.appearance);
    } else {
      $get('AvatarFallback').src = state.thumbUrl.replace(/x=\d+/, 'x=250');
    }
    var colors = state.appearance.colors;
    for (var part in this.partLabels) {
      if (!this.partLabels.hasOwnProperty(part)) continue;
      $get('Body_' + part).style.backgroundColor = BOBLOX.BrickColors[colors[part]].hex;
      $get('Body_' + part).title = this.partLabels[part] + ': ' + BOBLOX.BrickColors[colors[part]].name;
    }
    this.renderWearing();
    if (changed) {
      if (this.viewer.supported) this.scheduleThumbnail(600);
      else this.setStatus('Saved!');
    }
  },

  renderWearing: function () {
    var list = $get('WearingList'), html = '', w = this.state.wearing;
    if (!w.length) {
      list.innerHTML = '<p class="Faded">You are not wearing anything yet. Pick something from your wardrobe!</p>';
      return;
    }
    for (var i = 0; i < w.length; i++) {
      var name = String(w[i].name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
      html += '<div class="MiniAsset">' +
        '<a href="' + w[i].url + '"><img src="' + w[i].thumb + '" width="75" height="75" alt="' + name + '" title="' + name + '" border="0" /></a><br />' +
        '<span class="Faded">' + w[i].typeName + '</span><br />' +
        '<a href="' + w[i].url + '">' + name + '</a><br />' +
        '<a href="#" class="Button" onclick="BOBLOX.Character.remove(' + w[i].id + '); return false;">Remove</a>' +
        '</div>';
    }
    list.innerHTML = html;
  },

  // ---- body colours ----
  buildSwatches: function () {
    var holder = $get('ColorSwatches'), html = '';
    for (var i = 0; i < BOBLOX.BrickColors.length; i++) {
      var c = BOBLOX.BrickColors[i];
      html += '<a href="#" class="ColorSwatch" title="' + c.name + '" style="background-color:' + c.hex + '" ' +
        'onclick="BOBLOX.Character.chooseColor(' + c.id + '); return false;"></a>';
    }
    holder.innerHTML = html;
  },

  pickPart: function (part) {
    var old = document.querySelectorAll ? document.querySelectorAll('#BodyColorFigure .Selected') : [];
    for (var i = 0; i < old.length; i++) old[i].className = old[i].className.replace(/\s*Selected/g, '');
    this.pickingPart = part;
    $get('Body_' + part).className += ' Selected';
    $get('ColorPickerPart').innerHTML = this.partLabels[part];
    $get('ColorPicker').style.display = 'block';
  },

  closePicker: function () {
    $get('ColorPicker').style.display = 'none';
    if (this.pickingPart) $get('Body_' + this.pickingPart).className = $get('Body_' + this.pickingPart).className.replace(/\s*Selected/g, '');
    this.pickingPart = null;
  },

  chooseColor: function (colorId) {
    if (!this.pickingPart) return;
    this.lastColor = colorId;
    // show it immediately, the server answer confirms it
    $get('Body_' + this.pickingPart).style.backgroundColor = BOBLOX.BrickColors[colorId].hex;
    this.call('SetBodyColor', { part: this.pickingPart, colorId: colorId });
  },

  paintAll: function () {
    var color = this.lastColor;
    if (color === undefined) color = this.state.appearance.colors[this.pickingPart || 'Head'];
    if (!confirm('Paint your whole character ' + BOBLOX.BrickColors[color].name + '?')) return;
    this.call('SetAllBodyColors', { colorId: color });
  },

  // ---- wardrobe ----
  showWardrobe: function (type, page) {
    var self = this;
    this.currentType = type;
    this.currentPage = page;
    var tabs = $get('WardrobeTabs').getElementsByTagName('a');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].className = tabs[i].id === 'WardrobeTab_' + type ? 'Selected' : '';
    }
    var slugs = { 8: 'Hats', 18: 'Faces', 2: 'TShirts', 11: 'Shirts', 12: 'Pants' };
    var link = $get('WardrobeCatalogLink');
    link.href = link.href.replace(/(\?.*)?$/, '?c=' + slugs[type]);
    $get('WardrobeContent').style.opacity = 0.5;
    BOBLOX.Ajax.call('Character', 'GetWardrobe', { type: type, page: page }, function (r) {
      if (self.currentType !== type) return;
      $get('WardrobeContent').innerHTML = r.html;
      $get('WardrobeContent').style.opacity = 1;
    }, function (msg) {
      $get('WardrobeContent').innerHTML = '<p class="validationWarningSmall">' + msg.replace(/</g, '&lt;') + '</p>';
      $get('WardrobeContent').style.opacity = 1;
    });
  },

  refreshWardrobe: function () {
    this.showWardrobe(this.currentType, this.currentPage);
  },

  wear: function (itemId) {
    var self = this;
    this.call('WearItem', { itemId: itemId }, function () { self.refreshWardrobe(); });
  },

  remove: function (itemId) {
    var self = this;
    this.call('RemoveItem', { itemId: itemId }, function () { self.refreshWardrobe(); });
  },

  // ---- thumbnail for profiles ----
  scheduleThumbnail: function (delay) {
    var self = this;
    clearTimeout(this.thumbTimer);
    this.thumbTimer = setTimeout(function () { self.uploadThumbnail(); }, delay);
  },

  uploadThumbnail: function () {
    var self = this;
    if (this.busy > 0) { this.scheduleThumbnail(300); return; }
    var version = ++this.thumbVersion;
    this.viewer.snapshot(420, function (png) {
      if (version !== self.thumbVersion) return;   // something changed again meanwhile
      BOBLOX.Ajax.call('Character', 'UploadThumbnail', { png: png }, function () {
        if (version === self.thumbVersion && self.busy === 0) self.setStatus('Saved! Drag the figure to turn it.');
      }, function (msg) {
        self.setStatus('Saved, but the picture could not be updated: ' + msg.replace(/</g, '&lt;'), true);
      });
    });
  }
};

if (window.addEventListener) {
  window.addEventListener('load', function () { BOBLOX.Character.init(); });
} else if (window.attachEvent) {
  window.attachEvent('onload', function () { BOBLOX.Character.init(); });
}
