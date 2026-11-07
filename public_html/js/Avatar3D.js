/*
 * BOBLOX Avatar3D
 * Renders the 2006 figure (Asset/Figure/2006Figure.json, converted from
 * tools/figure/2006Figure.fbx) with three.js.
 *
 *   var viewer = new BOBLOX.Avatar3D(document.getElementById('Viewer'), { width: 300, height: 360 });
 *   viewer.setAppearance(appearance);   // see BOBLOX.Avatar3D.prototype.setAppearance
 *   viewer.snapshot(420, function (dataUrl) { ... });
 *
 * Requires js/three/three.min.js, js/BrickColors.js and js/Hats.js.
 */
var BOBLOX = window.BOBLOX || {};
window.BOBLOX = BOBLOX;

(function () {
  var ROOT = (BOBLOX.Config && BOBLOX.Config.root) || '/';
  var FIGURE_URL = ROOT + 'Asset/Figure/2006Figure.json';
  var SURFACES_URL = ROOT + 'Asset/Figure/surfaces.png';
  var FACES_URL = ROOT + 'Asset/Figure/faces.png';

  var BODY_PARTS = ['Head', 'Torso', 'LeftArm', 'RightArm', 'LeftLeg', 'RightLeg'];

  // Clothing template (585 x 559) rectangles: [x, y, w, h]
  var TEMPLATE_W = 585, TEMPLATE_H = 559;
  var TORSO_RECTS = {
    px: [361, 74, 64, 128],  // figure's left side
    nx: [165, 74, 64, 128],  // figure's right side
    py: [231, 8, 128, 64],
    ny: [231, 204, 128, 64],
    pz: [231, 74, 128, 128], // front
    nz: [427, 74, 128, 128]  // back
  };
  var RIGHT_LIMB_RECTS = {
    px: [19, 355, 64, 128], nz: [85, 355, 64, 128], nx: [151, 355, 64, 128], pz: [217, 355, 64, 128],
    py: [217, 289, 64, 64], ny: [217, 485, 64, 64]
  };
  var LEFT_LIMB_RECTS = {
    pz: [308, 355, 64, 128], px: [374, 355, 64, 128], nz: [440, 355, 64, 128], nx: [506, 355, 64, 128],
    py: [308, 289, 64, 64], ny: [308, 485, 64, 64]
  };
  var BOX_FACE_ORDER = ['px', 'nx', 'py', 'ny', 'pz', 'nz']; // THREE.BoxGeometry group order

  // ---- shared loading (figure json + base textures are fetched once per page) ----
  var shared = null, sharedWaiters = [];
  var textureCache = {};

  function loadJSON(url, cb) {
    var xhr = BOBLOX.Ajax ? BOBLOX.Ajax.createRequest() : new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status === 200) cb(JSON.parse(xhr.responseText));
      else cb(null);
    };
    xhr.send(null);
  }

  function loadTexture(url, cb) {
    if (textureCache[url]) {
      var c = textureCache[url];
      if (c.loaded) { cb(c.texture); } else { c.waiters.push(cb); }
      return;
    }
    var entry = { loaded: false, waiters: [cb], texture: null };
    textureCache[url] = entry;
    new THREE.TextureLoader().load(url, function (tex) {
      tex.encoding = THREE.sRGBEncoding;
      tex.anisotropy = 4;
      entry.texture = tex;
      entry.loaded = true;
      for (var i = 0; i < entry.waiters.length; i++) entry.waiters[i](tex);
      entry.waiters = [];
    }, undefined, function () {
      entry.loaded = true;
      for (var i = 0; i < entry.waiters.length; i++) entry.waiters[i](null);
      entry.waiters = [];
    });
  }

  function loadShared(cb) {
    if (shared) { cb(shared); return; }
    sharedWaiters.push(cb);
    if (sharedWaiters.length > 1) return;
    var result = {}, pending = 3;
    function done() {
      if (--pending) return;
      shared = result;
      for (var i = 0; i < sharedWaiters.length; i++) sharedWaiters[i](shared);
      sharedWaiters = [];
    }
    loadJSON(FIGURE_URL, function (json) { result.figure = json; done(); });
    loadTexture(SURFACES_URL, function (t) { result.surfaces = t; done(); });
    loadTexture(FACES_URL, function (t) { result.faces = t; done(); });
  }

  function buildGeometry(part) {
    var g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.Float32BufferAttribute(part.position, 3));
    g.setAttribute('normal', new THREE.Float32BufferAttribute(part.normal, 3));
    g.setAttribute('uv', new THREE.Float32BufferAttribute(part.uv, 2));
    for (var i = 0; i < part.groups.length; i++) {
      g.addGroup(part.groups[i].start, part.groups[i].count, i);
    }
    return g;
  }

  function overlayGeometry(box, inflate, rects) {
    var w = box.max[0] - box.min[0] + inflate * 2;
    var h = box.max[1] - box.min[1] + inflate * 2;
    var d = box.max[2] - box.min[2] + inflate * 2;
    var g = new THREE.BoxGeometry(w, h, d);
    var uv = g.attributes.uv;
    for (var gi = 0; gi < g.groups.length; gi++) {
      var grp = g.groups[gi];
      var r = rects[BOX_FACE_ORDER[gi]];
      // BoxGeometry is indexed; each face owns 4 consecutive vertices
      var firstVertex = gi * 4;
      for (var v = firstVertex; v < firstVertex + 4; v++) {
        var u0 = uv.getX(v), v0 = uv.getY(v);
        uv.setXY(v, (r[0] + u0 * r[2]) / TEMPLATE_W, 1 - (r[1] + (1 - v0) * r[3]) / TEMPLATE_H);
      }
      grp.materialIndex = 0;
    }
    uv.needsUpdate = true;
    g.clearGroups();
    g.translate((box.min[0] + box.max[0]) / 2, (box.min[1] + box.max[1]) / 2, (box.min[2] + box.max[2]) / 2);
    return g;
  }

  function colorColumn(id) {
    id = parseInt(id, 10);
    if (isNaN(id) || id < 0 || id >= 32) return 0;
    return id;
  }

  // =====================================================================
  BOBLOX.Avatar3D = function (container, options) {
    options = options || {};
    this.container = container;
    this.width = options.width || 300;
    this.height = options.height || 360;
    this.interactive = options.interactive !== false;
    this.yaw = options.yaw !== undefined ? options.yaw : -0.45;
    this.appearance = null;
    this.readyCallbacks = [];
    this.isReady = false;
    this.layers = {};
    this.supported = BOBLOX.Avatar3D.isSupported();

    if (!this.supported) {
      container.innerHTML = '<div class="Avatar3DUnsupported">Your browser does not support 3D (WebGL).</div>';
      return;
    }

    this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
    this.renderer.outputEncoding = THREE.sRGBEncoding;
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.setSize(this.width, this.height);
    this.renderer.setClearColor(0x000000, 0);
    container.appendChild(this.renderer.domElement);

    this.scene = new THREE.Scene();
    this.scene.add(new THREE.AmbientLight(0xffffff, 0.62));
    var sun = new THREE.DirectionalLight(0xffffff, 0.62);
    sun.position.set(-4, 8, 10);
    this.scene.add(sun);
    var fill = new THREE.DirectionalLight(0xffffff, 0.18);
    fill.position.set(6, 2, -6);
    this.scene.add(fill);

    this.figure = new THREE.Group();
    this.scene.add(this.figure);

    this.camera = new THREE.PerspectiveCamera(28, this.width / this.height, 0.1, 100);
    this.target = new THREE.Vector3(0, 2.75, 0);
    this.distance = 14.5;
    this._placeCamera();

    if (this.interactive) this._bindDrag();

    var self = this;
    loadShared(function (s) { self._build(s); });
  };

  BOBLOX.Avatar3D.isSupported = function () {
    try {
      var c = document.createElement('canvas');
      return !!(window.WebGLRenderingContext && (c.getContext('webgl') || c.getContext('experimental-webgl')));
    } catch (e) {
      return false;
    }
  };

  BOBLOX.Avatar3D.prototype._placeCamera = function () {
    var pitch = 0.12;
    this.camera.position.set(
      this.target.x + Math.sin(this.yaw) * Math.cos(pitch) * this.distance,
      this.target.y + Math.sin(pitch) * this.distance,
      this.target.z + Math.cos(this.yaw) * Math.cos(pitch) * this.distance
    );
    this.camera.lookAt(this.target);
  };

  BOBLOX.Avatar3D.prototype._bindDrag = function () {
    var self = this, el = this.renderer.domElement, dragging = false, lastX = 0;
    el.style.cursor = 'move';
    el.title = 'Drag to turn the figure';
    function down(x) { dragging = true; lastX = x; }
    function move(x) {
      if (!dragging) return;
      self.yaw -= (x - lastX) * 0.012;
      lastX = x;
      self._placeCamera();
      self.render();
    }
    function up() { dragging = false; }
    el.addEventListener('mousedown', function (e) { down(e.clientX); e.preventDefault(); });
    window.addEventListener('mousemove', function (e) { move(e.clientX); });
    window.addEventListener('mouseup', up);
    el.addEventListener('touchstart', function (e) { down(e.touches[0].clientX); }, { passive: true });
    el.addEventListener('touchmove', function (e) { move(e.touches[0].clientX); e.preventDefault(); }, { passive: false });
    el.addEventListener('touchend', up);
  };

  BOBLOX.Avatar3D.prototype._build = function (s) {
    if (!s.figure || !s.surfaces) {
      this.container.innerHTML = '<div class="Avatar3DUnsupported">Could not load the figure.</div>';
      return;
    }
    this.shared = s;
    var parts = s.figure.parts;
    this.parts = {};
    this.bodyMaterials = {};
    this._buildJoints(parts);

    for (var i = 0; i < BODY_PARTS.length; i++) {
      var name = BODY_PARTS[i];
      var tex = s.surfaces.clone();
      tex.needsUpdate = true;
      var mat = new THREE.MeshLambertMaterial({ map: tex });
      this.bodyMaterials[name] = mat;
      var materials = [mat];
      if (name === 'Head') {
        this.faceMaterial = new THREE.MeshLambertMaterial({ transparent: true, alphaTest: 0.05, polygonOffset: true, polygonOffsetFactor: -1, depthWrite: false });
        materials.push(this.faceMaterial);
      }
      var mesh = new THREE.Mesh(buildGeometry(parts[name]), materials);
      mesh.name = name;
      this.parts[name] = mesh;
      this._attach(name, mesh);
    }

    // T-shirt decal plane on the torso front
    this.tshirtMaterial = new THREE.MeshLambertMaterial({ transparent: true, alphaTest: 0.02, depthWrite: false, visible: false });
    this.tshirtMesh = new THREE.Mesh(buildGeometry(parts.TShirt), this.tshirtMaterial);
    this.tshirtMesh.renderOrder = 3;
    this.figure.add(this.tshirtMesh);

    // Clothing overlays (pants under shirt)
    this.pantsMaterial = new THREE.MeshLambertMaterial({ transparent: true, alphaTest: 0.02, depthWrite: false, visible: false });
    this.shirtMaterial = new THREE.MeshLambertMaterial({ transparent: true, alphaTest: 0.02, depthWrite: false, visible: false });
    var overlays = [
      ['Torso', 0.004, TORSO_RECTS, this.pantsMaterial, 1],
      ['LeftLeg', 0.005, LEFT_LIMB_RECTS, this.pantsMaterial, 1],
      ['RightLeg', 0.005, RIGHT_LIMB_RECTS, this.pantsMaterial, 1],
      ['Torso', 0.007, TORSO_RECTS, this.shirtMaterial, 2],
      ['LeftArm', 0.005, LEFT_LIMB_RECTS, this.shirtMaterial, 2],
      ['RightArm', 0.005, RIGHT_LIMB_RECTS, this.shirtMaterial, 2]
    ];
    for (var o = 0; o < overlays.length; o++) {
      var ov = overlays[o];
      var om = new THREE.Mesh(overlayGeometry(parts[ov[0]].box, ov[1], ov[2]), ov[3]);
      om.renderOrder = ov[4];
      this._attach(ov[0], om);
    }

    var head = parts.Head.box;
    this.hatAnchor = new THREE.Group();
    this.hatAnchor.position.set((head.min[0] + head.max[0]) / 2, head.max[1], (head.min[2] + head.max[2]) / 2);
    this.hatAnchor.userData.headSize = head.max[0] - head.min[0];
    this._attach('Head', this.hatAnchor);

    this.isReady = true;
    if (this.appearance) this._apply();
    else this.render();
    var cbs = this.readyCallbacks;
    this.readyCallbacks = [];
    for (var k = 0; k < cbs.length; k++) cbs[k](this);
  };

  // ---- poses: the head, arms and legs hang from joints (neck, shoulders, hips) ----
  BOBLOX.Avatar3D.prototype._buildJoints = function (parts) {
    var torsoTop = parts.Torso.box.max[1];
    function centre(box, k) { return (box.min[k] + box.max[k]) / 2; }
    var pivots = {
      Head: [centre(parts.Head.box, 0), torsoTop, 0],
      RightArm: [centre(parts.RightArm.box, 0), parts.RightArm.box.max[1] - 0.5, 0],
      LeftArm: [centre(parts.LeftArm.box, 0), parts.LeftArm.box.max[1] - 0.5, 0],
      RightLeg: [centre(parts.RightLeg.box, 0), parts.RightLeg.box.max[1], 0],
      LeftLeg: [centre(parts.LeftLeg.box, 0), parts.LeftLeg.box.max[1], 0]
    };
    this.joints = {};
    for (var name in pivots) {
      if (!pivots.hasOwnProperty(name)) continue;
      var g = new THREE.Group();
      g.position.set(pivots[name][0], pivots[name][1], pivots[name][2]);
      if (name === 'Head') g.rotation.order = 'YXZ';
      this.figure.add(g);
      this.joints[name] = g;
    }
  };

  // Puts a part (or something stuck to it) under its joint, keeping it where it is.
  BOBLOX.Avatar3D.prototype._attach = function (partName, object) {
    var joint = this.joints[partName];
    if (!joint) { this.figure.add(object); return; }
    object.position.sub(joint.position);
    joint.add(object);
  };

  /*
   * joints = { Head: {yaw, pitch, roll}, RightArm: {swing, raise}, LeftArm: {...},
   *            RightLeg: {swing, raise}, LeftLeg: {...} }   (degrees, see App_Code/Poses.php)
   */
  BOBLOX.Avatar3D.prototype.setPose = function (joints) {
    this.poseJoints = joints || {};
    if (!this.isReady) return;
    var d = Math.PI / 180, j = this.poseJoints;
    function a(joint, axis) { return j[joint] && j[joint][axis] ? +j[joint][axis] * d : 0; }
    this.joints.Head.rotation.set(a('Head', 'pitch'), a('Head', 'yaw'), a('Head', 'roll'));
    this.joints.RightArm.rotation.set(-a('RightArm', 'swing'), 0, -a('RightArm', 'raise'));
    this.joints.LeftArm.rotation.set(-a('LeftArm', 'swing'), 0, a('LeftArm', 'raise'));
    this.joints.RightLeg.rotation.set(-a('RightLeg', 'swing'), 0, -a('RightLeg', 'raise'));
    this.joints.LeftLeg.rotation.set(-a('LeftLeg', 'swing'), 0, a('LeftLeg', 'raise'));
    this.render();
  };

  BOBLOX.Avatar3D.prototype.ready = function (cb) {
    if (this.isReady) cb(this);
    else this.readyCallbacks.push(cb);
  };

  /*
   * appearance = {
   *   colors: { Head: 6, Torso: 8, LeftArm: 6, RightArm: 6, LeftLeg: 19, RightLeg: 19 },  // BrickColors index
   *   face:   { slot: 0 } | { image: '/Uploads/...png' },
   *   tshirt: '/Uploads/...png' | null,
   *   shirt:  '/Uploads/...png' | null,
   *   pants:  '/Uploads/...png' | null,
   *   hat:    { mesh: 'tophat', color: '#222222' } | null,
   *   pose:   { name: 'wave', joints: {...} }  (optional)
   * }
   */
  BOBLOX.Avatar3D.prototype.setAppearance = function (appearance) {
    this.appearance = appearance || {};
    if (this.isReady) this._apply();
  };

  BOBLOX.Avatar3D.prototype._apply = function () {
    var a = this.appearance, self = this;
    var colors = a.colors || {};
    for (var i = 0; i < BODY_PARTS.length; i++) {
      var name = BODY_PARTS[i];
      var map = this.bodyMaterials[name].map;
      map.offset.x = colorColumn(colors[name]) / this.shared.figure.columns;
    }

    // face
    var face = a.face || { slot: 0 };
    if (face.image) {
      this._layerTexture('face', face.image, function (tex) {
        if (tex) {
          tex.repeat.set(1, 4);
          tex.offset.set(0, -3);
          self.faceMaterial.map = tex;
        }
        self.faceMaterial.needsUpdate = true;
        self.render();
      });
    } else {
      this.layers.face = null;
      var ft = this.faceSlotTexture || (this.faceSlotTexture = this.shared.faces.clone());
      ft.needsUpdate = true;
      ft.offset.set(0, -(parseInt(face.slot, 10) || 0) / this.shared.figure.faceSlots);
      this.faceMaterial.map = ft;
      this.faceMaterial.needsUpdate = true;
    }

    this._applyLayer('tshirt', a.tshirt, this.tshirtMaterial);
    this._applyLayer('shirt', a.shirt, this.shirtMaterial);
    this._applyLayer('pants', a.pants, this.pantsMaterial);

    // hat
    while (this.hatAnchor.children.length) this.hatAnchor.remove(this.hatAnchor.children[0]);
    if (a.hat && a.hat.mesh && BOBLOX.Hats && BOBLOX.Hats[a.hat.mesh]) {
      var hat = BOBLOX.Hats[a.hat.mesh].build(THREE, a.hat.color, this.hatAnchor.userData.headSize);
      this.hatAnchor.add(hat);
    }
    this.setPose(a.pose ? a.pose.joints : this.poseJoints);
  };

  // Load a clothing texture into a material, ignoring stale responses.
  BOBLOX.Avatar3D.prototype._layerTexture = function (layer, url, cb) {
    var self = this;
    this.layers[layer] = url;
    loadTexture(url, function (tex) {
      if (self.layers[layer] !== url) return; // changed again while loading
      if (tex) {
        tex = tex.clone();
        tex.needsUpdate = true;
      }
      cb(tex);
    });
  };

  BOBLOX.Avatar3D.prototype._applyLayer = function (layer, url, material) {
    var self = this;
    if (!url) {
      this.layers[layer] = null;
      material.visible = false;
      return;
    }
    this._layerTexture(layer, url, function (tex) {
      material.map = tex;
      material.visible = !!tex;
      material.needsUpdate = true;
      self.render();
    });
  };

  BOBLOX.Avatar3D.prototype.render = function () {
    if (!this.renderer) return;
    this.renderer.render(this.scene, this.camera);
  };

  BOBLOX.Avatar3D.prototype.isLoading = function () {
    for (var k in this.layers) {
      if (this.layers[k] && textureCache[this.layers[k]] && !textureCache[this.layers[k]].loaded) return true;
    }
    return false;
  };

  // Renders a square, transparent PNG thumbnail from the classic angle.
  BOBLOX.Avatar3D.prototype.snapshot = function (size, cb) {
    var self = this;
    this.ready(function () {
      var tries = 0;
      (function wait() {
        if (self.isLoading() && tries++ < 100) { setTimeout(wait, 50); return; }
        var r = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
        r.outputEncoding = THREE.sRGBEncoding;
        r.setPixelRatio(1);
        r.setSize(size, size);
        r.setClearColor(0x000000, 0);
        var cam = new THREE.PerspectiveCamera(26, 1, 0.1, 100);
        var yaw = -0.45, pitch = 0.12, dist = 15.5, t = new THREE.Vector3(0, 2.95, 0);
        cam.position.set(t.x + Math.sin(yaw) * Math.cos(pitch) * dist, t.y + Math.sin(pitch) * dist, t.z + Math.cos(yaw) * Math.cos(pitch) * dist);
        cam.lookAt(t);
        r.render(self.scene, cam);
        var url = r.domElement.toDataURL('image/png');
        r.dispose();
        if (r.forceContextLoss) r.forceContextLoss();
        cb(url);
      })();
    });
  };
})();
