// Renders the catalog pictures for the built-in hats (public_html/img/Hats/*.png).
//   php -S 127.0.0.1:8099 -t public_html public_html/router.php &
//   node tools/render-hats.cjs http://127.0.0.1:8099 public_html/img/Hats
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

(async () => {
  const [base, outDir] = process.argv.slice(2);
  const browser = await chromium.launch({ args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const page = await browser.newPage();
  await page.setContent(`<html><body>
    <script src="${base}/js/three/three.min.js"></script>
    <script src="${base}/js/Hats.js"></script></body></html>`);
  await page.waitForFunction(() => window.THREE && window.BOBLOX && BOBLOX.Hats);
  const shots = await page.evaluate(() => {
    const out = {};
    const r = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
    r.outputEncoding = THREE.sRGBEncoding;
    r.setSize(420, 420);
    r.setClearColor(0x000000, 0);
    // every hat in its default colour, plus coloured variants used by catalog items
    const jobs = Object.keys(BOBLOX.Hats).map(k => [k, null]).concat([['cap', '#0e6aac']]);
    for (const [key, color] of jobs) {
      const scene = new THREE.Scene();
      scene.add(new THREE.AmbientLight(0xffffff, 0.62));
      const sun = new THREE.DirectionalLight(0xffffff, 0.7); sun.position.set(-4, 8, 10); scene.add(sun);
      const hat = BOBLOX.Hats[key].build(THREE, color, 1.23);
      scene.add(hat);
      const box = new THREE.Box3().setFromObject(hat);
      const c = box.getCenter(new THREE.Vector3()), size = box.getSize(new THREE.Vector3());
      const cam = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
      const d = Math.max(size.x, size.y, size.z) * 2.4;
      cam.position.set(c.x - d * 0.42, c.y + d * 0.35, c.z + d * 0.85);
      cam.lookAt(c);
      r.render(scene, cam);
      out[color ? key + '_' + color.slice(1) : key] = r.domElement.toDataURL('image/png');
    }
    return out;
  });
  fs.mkdirSync(outDir, { recursive: true });
  for (const [k, v] of Object.entries(shots)) {
    fs.writeFileSync(path.join(outDir, k + '.png'), Buffer.from(v.split(',')[1], 'base64'));
  }
  fs.copyFileSync(path.join(outDir, 'tophat.png'), path.join(outDir, 'unknown.png'));
  console.log('rendered', Object.keys(shots).join(', '));
  await browser.close();
})();
