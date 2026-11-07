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
    this.buildPosePanel();
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
      // keep an unsaved custom pose on screen while other things change
      if (this.poseDirty) this.viewer.setPose(this.readSliders());
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
    if (!this.poseDirty) this.showPose(state.appearance.pose);
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

  // ---- poses ----
  jointLabels: { Head: 'Head', RightArm: 'Right Arm', LeftArm: 'Left Arm', RightLeg: 'Right Leg', LeftLeg: 'Left Leg' },
  axisLabels: { yaw: 'Turn', pitch: 'Nod', roll: 'Tilt', swing: 'Swing', raise: 'Raise' },

  buildPosePanel: function () {
    var root = (BOBLOX.Config && BOBLOX.Config.root) || '/';
    var html = '', presets = BOBLOX.Poses.presets, i;
    for (i = 0; i < presets.length; i++) {
      html += '<a href="#" class="PosePreset" id="Pose_' + presets[i].name + '" title="' + presets[i].label + '" ' +
        'onclick="BOBLOX.Character.choosePose(\'' + presets[i].name + '\'); return false;">' +
        '<img src="' + root + 'img/Poses/' + presets[i].name + '.png" alt="" />' + presets[i].label + '</a>';
    }
    $get('PosePresets').innerHTML = html;

    var limits = BOBLOX.Poses.limits, sliders = '';
    for (var joint in this.jointLabels) {
      if (!this.jointLabels.hasOwnProperty(joint)) continue;
      sliders += '<div class="PoseJoint"><b>' + this.jointLabels[joint] + '</b>';
      for (var axis in limits[joint]) {
        if (!limits[joint].hasOwnProperty(axis)) continue;
        var id = 'Pose_' + joint + '_' + axis;
        sliders += '<div class="PoseSlider"><label for="' + id + '">' + this.axisLabels[axis] + '</label>' +
          '<input type="range" id="' + id + '" min="' + limits[joint][axis][0] + '" max="' + limits[joint][axis][1] + '" step="1" value="0" ' +
          'oninput="BOBLOX.Character.previewCustomPose()" onchange="BOBLOX.Character.previewCustomPose()" />' +
          '<span id="' + id + '_Value">0&deg;</span></div>';
      }
      sliders += '</div>';
    }
    $get('PoseSliders').innerHTML = sliders;
  },

  readSliders: function () {
    var limits = BOBLOX.Poses.limits, joints = {};
    for (var joint in limits) {
      if (!limits.hasOwnProperty(joint)) continue;
      joints[joint] = {};
      for (var axis in limits[joint]) {
        if (limits[joint].hasOwnProperty(axis)) joints[joint][axis] = parseFloat($get('Pose_' + joint + '_' + axis).value) || 0;
      }
    }
    return joints;
  },

  writeSliders: function (joints) {
    var limits = BOBLOX.Poses.limits;
    for (var joint in limits) {
      if (!limits.hasOwnProperty(joint)) continue;
      for (var axis in limits[joint]) {
        if (!limits[joint].hasOwnProperty(axis)) continue;
        var v = joints[joint] && joints[joint][axis] ? Math.round(joints[joint][axis]) : 0;
        $get('Pose_' + joint + '_' + axis).value = v;
        $get('Pose_' + joint + '_' + axis + '_Value').innerHTML = v + '&deg;';
      }
    }
  },

  // Highlights the saved pose and loads its angles into the sliders.
  showPose: function (pose) {
    if (!pose) return;
    var presets = BOBLOX.Poses.presets;
    for (var i = 0; i < presets.length; i++) {
      var el = $get('Pose_' + presets[i].name);
      el.className = 'PosePreset' + (presets[i].name === pose.name ? ' Selected' : '');
    }
    this.writeSliders(pose.joints);
    this.poseDirty = false;
    $get('PoseName').innerHTML = 'Current pose: ' + pose.label;
  },

  choosePose: function (name) {
    var self = this;
    this.poseDirty = false;
    this.call('SetPose', { name: name }, function () { self.showPose(self.state.appearance.pose); });
  },

  previewCustomPose: function () {
    var joints = this.readSliders();
    this.writeSliders(joints);
    this.poseDirty = true;
    $get('PoseName').innerHTML = 'Custom pose (not saved yet)';
    var presets = BOBLOX.Poses.presets;
    for (var i = 0; i < presets.length; i++) $get('Pose_' + presets[i].name).className = 'PosePreset';
    if (this.viewer.supported) this.viewer.setPose(joints);
  },

  saveCustomPose: function () {
    var self = this;
    this.poseDirty = false;
    this.call('SetPose', { name: 'custom', joints: this.readSliders() }, function () { self.showPose(self.state.appearance.pose); });
  },

  resetPose: function () {
    var pose = this.state.appearance.pose;
    this.showPose(pose);
    if (this.viewer.supported) {
      this.viewer.setPose(pose.joints);
      if (!this.state.thumbRendered) this.scheduleThumbnail(300);
    }
  },

  toggleCustomPose: function () {
    var ed = $get('CustomPoseEditor'), open = ed.style.display === 'none';
    ed.style.display = open ? 'block' : 'none';
    $get('CustomPoseToggle').innerHTML = open ? '[-] Hide the custom pose editor' : '[+] Make a custom pose';
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
    if (this.poseDirty) {
      // the profile picture is made once the custom pose is saved or undone
      this.setStatus('Saved! Remember to save your custom pose.');
      return;
    }
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
