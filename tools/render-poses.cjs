// Renders the pose picker icons (public_html/img/Poses/*.png): the classic figure in every preset pose.
//   php -S 127.0.0.1:8099 -t public_html public_html/router.php &
//   node tools/render-poses.cjs http://127.0.0.1:8099 public_html/img/Poses
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

(async () => {
  const [base, outDir] = process.argv.slice(2);
  const presets = JSON.parse(execFileSync('php', ['-r',
    'require "public_html/App_Code/Config.php"; require "public_html/App_Code/Poses.php"; echo json_encode(pose_client_data()["presets"]);'
  ], { cwd: path.join(__dirname, '..') }).toString());

  const browser = await chromium.launch({ args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const page = await browser.newPage();
  await page.goto(base + '/Default.aspx');
  await page.setContent(`<html><body><div id="v"></div>
    <script>var BOBLOX = { Config: { root: '${base}/' } };</script>
    <script src="${base}/js/three/three.min.js"></script>
    <script src="${base}/js/BrickColors.js"></script>
    <script src="${base}/js/Hats.js"></script>
    <script src="${base}/js/Avatar3D.js"></script></body></html>`);
  const shots = await page.evaluate((presets) => new Promise(resolve => {
    const viewer = new BOBLOX.Avatar3D(document.getElementById('v'), { width: 64, height: 64, interactive: false });
    const look = { colors: { Head: 6, Torso: 8, LeftArm: 6, RightArm: 6, LeftLeg: 19, RightLeg: 19 }, face: { slot: 0 } };
    const out = {};
    let i = 0;
    (function next() {
      if (i >= presets.length) return resolve(out);
      const p = presets[i++];
      look.pose = p;
      viewer.setAppearance(look);
      viewer.snapshot(128, url => { out[p.name] = url; next(); });
    })();
  }), presets);
  fs.mkdirSync(outDir, { recursive: true });
  for (const [k, v] of Object.entries(shots)) {
    fs.writeFileSync(path.join(outDir, k + '.png'), Buffer.from(v.split(',')[1], 'base64'));
  }
  console.log('rendered', Object.keys(shots).join(', '));
  await browser.close();
})();
