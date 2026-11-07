/*
 * Built-in hats, built from simple three.js shapes.
 * Each builder returns a THREE.Group whose origin sits on the top-centre of the head.
 * `s` is the head width (about 1.23 units for the 2006 figure).
 * The keys must match the `mesh` column of hat items (App_Code/Database.php seeds them).
 */
var BOBLOX = window.BOBLOX || {};
window.BOBLOX = BOBLOX;

(function () {
  function mat(THREE, color) {
    return new THREE.MeshLambertMaterial({ color: new THREE.Color(color).convertSRGBToLinear() });
  }
  function add(group, mesh, x, y, z) {
    mesh.position.set(x || 0, y || 0, z || 0);
    group.add(mesh);
    return mesh;
  }

  BOBLOX.Hats = {
    tophat: {
      name: 'Top Hat',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#1b1b1b');
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.62, s * 0.62, s * 0.05, 32), m), 0, -0.02);
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.38, s * 0.38, s * 0.8, 32), m), 0, s * 0.4);
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.385, s * 0.385, s * 0.12, 32), mat(THREE, '#a8231b')), 0, s * 0.1);
        return g;
      }
    },
    cone: {
      name: 'Traffic Cone',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#f07a1a');
        add(g, new THREE.Mesh(new THREE.BoxGeometry(s * 0.95, s * 0.06, s * 0.95), m), 0, -0.03);
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.05, s * 0.4, s * 1.25, 32), m), 0, s * 0.62);
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.2, s * 0.27, s * 0.22, 32), mat(THREE, '#f4f4f4')), 0, s * 0.62);
        return g;
      }
    },
    cap: {
      name: 'Baseball Cap',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#c4291c');
        var dome = new THREE.Mesh(new THREE.SphereGeometry(s * 0.52, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2), m);
        dome.scale.set(1, 0.65, 1);
        add(g, dome, 0, -s * 0.12);
        var visor = new THREE.Mesh(new THREE.CylinderGeometry(s * 0.42, s * 0.42, s * 0.04, 32, 1, false, -Math.PI / 2, Math.PI), m);
        visor.scale.set(1, 1, 1.15);
        add(g, visor, 0, -s * 0.11, s * 0.28);
        add(g, new THREE.Mesh(new THREE.SphereGeometry(s * 0.06, 12, 8), m), 0, s * 0.22);
        return g;
      }
    },
    party: {
      name: 'Party Hat',
      build: function (THREE, color, s) {
        var g = new THREE.Group();
        var hat = add(g, new THREE.Mesh(new THREE.ConeGeometry(s * 0.32, s * 0.95, 32), mat(THREE, color || '#6c337c')), 0, s * 0.42);
        hat.rotation.z = -0.12;
        add(g, new THREE.Mesh(new THREE.SphereGeometry(s * 0.09, 12, 8), mat(THREE, '#f5cd30')), s * 0.11, s * 0.92);
        add(g, new THREE.Mesh(new THREE.TorusGeometry(s * 0.3, s * 0.035, 8, 32), mat(THREE, '#f5cd30')), 0, s * 0.02).rotation.x = Math.PI / 2;
        return g;
      }
    },
    crown: {
      name: 'Golden Crown',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#e2b13c');
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.42, s * 0.4, s * 0.22, 32, 1, true), m), 0, s * 0.06);
        m.side = THREE.DoubleSide;
        for (var i = 0; i < 8; i++) {
          var a = i / 8 * Math.PI * 2;
          add(g, new THREE.Mesh(new THREE.ConeGeometry(s * 0.08, s * 0.24, 8), m), Math.sin(a) * s * 0.41, s * 0.28, Math.cos(a) * s * 0.41);
          add(g, new THREE.Mesh(new THREE.SphereGeometry(s * 0.045, 8, 6), mat(THREE, i % 2 ? '#c4291c' : '#0e6aac')), Math.sin(a) * s * 0.425, s * 0.07, Math.cos(a) * s * 0.425);
        }
        return g;
      }
    },
    viking: {
      name: 'Viking Helmet',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#8a8d91'), horn = mat(THREE, '#efe6c8');
        var dome = add(g, new THREE.Mesh(new THREE.SphereGeometry(s * 0.56, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2), m), 0, -s * 0.2);
        dome.scale.set(1, 0.8, 1);
        add(g, new THREE.Mesh(new THREE.TorusGeometry(s * 0.56, s * 0.05, 8, 32), mat(THREE, '#6a4128')), 0, -s * 0.2).rotation.x = Math.PI / 2;
        for (var side = -1; side <= 1; side += 2) {
          var h = add(g, new THREE.Mesh(new THREE.ConeGeometry(s * 0.1, s * 0.5, 16), horn), side * s * 0.62, s * 0.12);
          h.rotation.z = -side * 0.9;
        }
        return g;
      }
    },
    chef: {
      name: 'Chef Hat',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#f7f7f7');
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.45, s * 0.45, s * 0.32, 32), m), 0, s * 0.1);
        var puff = add(g, new THREE.Mesh(new THREE.SphereGeometry(s * 0.55, 32, 16), m), 0, s * 0.48);
        puff.scale.set(1, 0.6, 1);
        return g;
      }
    },
    halo: {
      name: 'Shiny Halo',
      build: function (THREE, color, s) {
        var g = new THREE.Group();
        var m = new THREE.MeshLambertMaterial({ color: new THREE.Color(color || '#ffe66b').convertSRGBToLinear(), emissive: new THREE.Color('#8a6b00') });
        add(g, new THREE.Mesh(new THREE.TorusGeometry(s * 0.4, s * 0.06, 12, 40), m), 0, s * 0.3).rotation.x = Math.PI / 2;
        return g;
      }
    },
    cowboy: {
      name: 'Cowboy Hat',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#7d5d46');
        var brim = add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.85, s * 0.85, s * 0.05, 32), m), 0, -s * 0.03);
        brim.scale.set(1, 1, 0.85);
        var crown = add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.34, s * 0.42, s * 0.48, 32), m), 0, s * 0.23);
        crown.scale.set(1, 1, 0.85);
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.425, s * 0.425, s * 0.08, 32), mat(THREE, '#1c2b35')), 0, s * 0.04).scale.set(1, 1, 0.85);
        return g;
      }
    },
    beanie: {
      name: 'Knit Beanie',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#0e6aac');
        var dome = add(g, new THREE.Mesh(new THREE.SphereGeometry(s * 0.54, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2), m), 0, -s * 0.18);
        dome.scale.set(1, 0.9, 1);
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.56, s * 0.56, s * 0.16, 32), mat(THREE, '#f2f3f2')), 0, -s * 0.18);
        add(g, new THREE.Mesh(new THREE.SphereGeometry(s * 0.14, 12, 8), mat(THREE, '#f2f3f2')), 0, s * 0.33);
        return g;
      }
    },
    bucket: {
      name: 'Bucket Hat',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#799182');
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.68, s * 0.68, s * 0.04, 32), m), 0, -s * 0.06);
        add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.4, s * 0.5, s * 0.36, 32), m), 0, s * 0.1);
        return g;
      }
    },
    antlers: {
      name: 'Moose Antlers',
      build: function (THREE, color, s) {
        var g = new THREE.Group(), m = mat(THREE, color || '#a16035');
        for (var side = -1; side <= 1; side += 2) {
          var stem = add(g, new THREE.Mesh(new THREE.CylinderGeometry(s * 0.05, s * 0.07, s * 0.5, 8), m), side * s * 0.32, s * 0.1);
          stem.rotation.z = -side * 0.5;
          var palm = add(g, new THREE.Mesh(new THREE.BoxGeometry(s * 0.55, s * 0.32, s * 0.06), m), side * s * 0.62, s * 0.42);
          palm.rotation.z = -side * 0.35;
          for (var t = 0; t < 3; t++) {
            add(g, new THREE.Mesh(new THREE.ConeGeometry(s * 0.04, s * 0.18, 6), m), side * s * (0.45 + t * 0.14), s * (0.62 + t * 0.03));
          }
        }
        return g;
      }
    }
  };
})();
