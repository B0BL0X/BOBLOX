// Converts the 2006 figure FBX into the small JSON mesh file the site loads.
//
//   cd tools && npm install && npm run convert-figure
//
// What it does:
//  * bakes the skinned meshes into their bind pose (world space),
//  * turns the figure so it faces +Z (towards the default camera),
//  * splits the combined arm mesh into Left Arm / Right Arm,
//  * rebases every body part's UVs into colour column 0 of surfaces.png, so the
//    browser can recolour a part by shifting the texture by (column / 32).
import fs from 'fs';

globalThis.self = globalThis;
globalThis.window = globalThis;
globalThis.document = { createElementNS() { return { style: {}, addEventListener() {}, removeEventListener() {} }; } };

const THREE = await import('three');
const { FBXLoader } = await import('three/examples/jsm/loaders/FBXLoader.js');
THREE.TextureLoader.prototype.load = function () { return new THREE.Texture(); };

const [, , input, output] = process.argv;
if (!input || !output) {
  console.error('usage: node convert.mjs <in.fbx> <out.json>');
  process.exit(1);
}

const buf = fs.readFileSync(input);
const root = new FBXLoader().parse(buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength), '');
root.updateMatrixWorld(true);

const SCALE = 1 / 100;
const COLUMNS = 32;
const meshes = [];
root.traverse(o => { if (o.isMesh) meshes.push(o); });

// Bake one mesh (or a subset of its triangles) into plain arrays.
function bake(mesh, triFilter, rebaseUV) {
  const g = mesh.geometry;
  const pos = g.attributes.position, nor = g.attributes.normal, uv = g.attributes.uv;
  const normalMatrix = new THREE.Matrix3().getNormalMatrix(mesh.matrixWorld);
  const p = new THREE.Vector3(), n = new THREE.Vector3();
  const out = { position: [], normal: [], uv: [], groups: [] };

  const groups = g.groups.length ? g.groups : [{ start: 0, count: pos.count, materialIndex: 0 }];
  for (const grp of groups) {
    const first = out.position.length / 3;
    for (let t = grp.start; t < grp.start + grp.count; t += 3) {
      const tri = [t, t + 1, t + 2].map(i => {
        mesh.getVertexPosition(i, p);
        p.applyMatrix4(mesh.matrixWorld).multiplyScalar(SCALE);
        n.fromBufferAttribute(nor, i).applyMatrix3(normalMatrix).normalize();
        // rotate 180deg about Y so the figure faces +Z
        return { p: [-p.x, p.y, -p.z], n: [-n.x, n.y, -n.z], uv: [uv.getX(i), uv.getY(i)] };
      });
      if (triFilter && !triFilter(tri)) continue;
      for (const v of tri) {
        out.position.push(...v.p);
        out.normal.push(...v.n);
        out.uv.push(...v.uv);
      }
    }
    out.groups.push({ start: first, count: out.position.length / 3 - first, material: grp.materialIndex });
  }

  if (rebaseUV) {
    // Every body part samples a single colour column; move it to column 0.
    let umin = Infinity;
    const g0 = out.groups[0];
    for (let i = g0.start; i < g0.start + g0.count; i++) umin = Math.min(umin, out.uv[i * 2]);
    const column = Math.floor(umin * COLUMNS + 1e-4);
    const lo = 0.5 / 2048, hi = 1 / COLUMNS - 0.5 / 2048;
    for (let i = g0.start; i < g0.start + g0.count; i++) {
      out.uv[i * 2] = Math.min(hi, Math.max(lo, out.uv[i * 2] - column / COLUMNS));
    }
    out.sourceColumn = column;
  }

  // bounding box (used for clothing overlays)
  const min = [Infinity, Infinity, Infinity], max = [-Infinity, -Infinity, -Infinity];
  for (let i = 0; i < out.position.length; i += 3) {
    for (let k = 0; k < 3; k++) {
      min[k] = Math.min(min[k], out.position[i + k]);
      max[k] = Math.max(max[k], out.position[i + k]);
    }
  }
  out.box = { min, max };
  const r = a => a.map(v => Math.round(v * 10000) / 10000);
  out.position = r(out.position); out.normal = r(out.normal); out.uv = r(out.uv);
  out.box.min = r(min); out.box.max = r(max);
  return out;
}

function bbox(mesh) {
  return new THREE.Box3().setFromObject(mesh);
}

const parts = {};
for (const m of meshes) {
  const b = bbox(m);
  const vcount = m.geometry.attributes.position.count;
  const sizeX = b.max.x - b.min.x;
  if (m.geometry.groups.length > 1) {
    parts.Head = bake(m, null, true);
  } else if (vcount === 6) {
    parts.TShirt = bake(m, null, false);
  } else if (sizeX > 300) {
    // both arms in one mesh: split on the sign of X (after the 180deg turn)
    const cx = tri => (tri[0].p[0] + tri[1].p[0] + tri[2].p[0]) / 3;
    parts.LeftArm = bake(m, tri => cx(tri) > 0, true);
    parts.RightArm = bake(m, tri => cx(tri) < 0, true);
  } else if (b.min.y < 50) {
    // legs; after the turn the figure's left side is +X
    const centreAfterTurn = -(b.min.x + b.max.x) / 2;
    parts[centreAfterTurn > 0 ? 'LeftLeg' : 'RightLeg'] = bake(m, null, true);
  } else {
    parts.Torso = bake(m, null, true);
  }
}

const required = ['Head', 'Torso', 'LeftArm', 'RightArm', 'LeftLeg', 'RightLeg', 'TShirt'];
for (const k of required) if (!parts[k]) throw new Error('missing part ' + k);

fs.writeFileSync(output, JSON.stringify({
  name: '2006Figure',
  source: 'tools/figure/2006Figure.fbx',
  columns: COLUMNS,
  faceSlots: 4,
  parts,
}));
console.log('wrote', output, Object.fromEntries(Object.entries(parts).map(([k, v]) => [k, { verts: v.position.length / 3, box: v.box, col: v.sourceColumn }])));
