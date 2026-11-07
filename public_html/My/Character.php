<?php
/* The avatar changer: body colours, hats, faces, T-Shirts, Shirts and Pants. */
require_once __DIR__ . '/../App_Code/Bootstrap.php';
require_once __DIR__ . '/../App_Code/CharacterState.php';
$me = require_login();

$startType = get_int('type', ITEM_HAT);
if (!isset(item_types()[$startType])) $startType = ITEM_HAT;
$parts = array('Head' => 'Head', 'Torso' => 'Torso', 'RightArm' => 'Right Arm', 'LeftArm' => 'Left Arm', 'RightLeg' => 'Right Leg', 'LeftLeg' => 'Left Leg');
$colors = avatar_colors($me);

page_header('Character', array('three' => true, 'scripts' => array('/js/Character.js')));
?>
<script type="text/javascript">
//<![CDATA[
BOBLOX.CharacterStartState = <?php echo json_encode(character_state($me), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
BOBLOX.CharacterStartType = <?php echo (int)$startType; ?>;
BOBLOX.Poses = <?php echo json_encode(pose_client_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
//]]>
</script>
<div id="CharacterContainer">
<?php if (get_int('welcome')) { ?>
  <div class="WelcomeNote"><?php echo multiline(str_replace('{name}', $me['username'], setting('welcome_note'))); ?></div>
<?php } ?>
  <div id="CharacterLeft">
    <div class="Panel" id="AvatarPanel">
      <h4>My Character</h4>
      <div class="PanelContent">
        <div id="AvatarViewer"></div>
        <img id="AvatarFallback" src="<?php echo h(avatar_thumb_url($me, 250)); ?>" width="250" height="250" alt="" style="display:none" />
        <div id="AvatarStatus" class="StatusMessage">Drag the figure to turn it.</div>
        <div class="AvatarLinks"><a href="<?php echo h(profile_url($me['id'])); ?>">View my profile</a></div>
      </div>
    </div>
    <div class="Panel" id="BodyColorsPanel">
      <h4>Body Colors</h4>
      <div class="PanelContent">
        <p class="Hint">Click a body part, then choose a colour.</p>
        <div id="BodyColorFigure">
<?php foreach ($parts as $part => $label) { ?>
          <div id="Body_<?php echo $part; ?>" class="BodyPart <?php echo $part; ?>" title="<?php echo h($label); ?>" style="background-color:<?php echo h(brick_color_hex($colors[$part])); ?>" onclick="BOBLOX.Character.pickPart('<?php echo $part; ?>')"></div>
<?php } ?>
        </div>
        <div id="ColorPicker" style="display:none">
          <div class="ColorPickerTitle">Choose a colour for the <span id="ColorPickerPart"></span>:</div>
          <div id="ColorSwatches"></div>
          <div class="ColorPickerFooter">
            <a href="#" onclick="BOBLOX.Character.paintAll(); return false;">Use this colour everywhere</a> |
            <a href="#" onclick="BOBLOX.Character.closePicker(); return false;">Close</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div id="CharacterRight">
    <div class="Panel" id="WardrobePanel">
      <h4>Wardrobe</h4>
      <div class="PanelContent">
        <div id="WardrobeTabs" class="CreateTabs">
<?php foreach (item_types() as $t => $info) { ?>
          <a href="#" id="WardrobeTab_<?php echo $t; ?>" onclick="BOBLOX.Character.showWardrobe(<?php echo $t; ?>, 1); return false;"><?php echo h($info[1]); ?></a>
<?php } ?>
        </div>
        <div id="WardrobeContent"><p class="Faded">Loading...</p></div>
        <div class="PanelFooter">
          <a href="<?php echo h(url('/Catalog.aspx')); ?>" id="WardrobeCatalogLink">Get more from the catalog</a> |
          <a href="<?php echo h(url('/My/Create.aspx')); ?>">Make your own</a>
        </div>
      </div>
    </div>
    <div class="Panel" id="PosePanel">
      <h4>Pose</h4>
      <div class="PanelContent">
        <div id="PosePresets"></div>
        <div style="clear:both"></div>
        <div id="CustomPose">
          <div class="CustomPoseTitle">
            <a href="#" onclick="BOBLOX.Character.toggleCustomPose(); return false;" id="CustomPoseToggle">[+] Make a custom pose</a>
            <span id="PoseName" class="Faded"></span>
          </div>
          <div id="CustomPoseEditor" style="display:none">
            <p class="Hint">Move the sliders to bend the head, arms and legs. The figure shows the pose straight away; press Save Pose to keep it.</p>
            <div id="PoseSliders"></div>
            <div style="clear:both"></div>
            <div class="CustomPoseButtons">
              <a href="#" class="Button" onclick="BOBLOX.Character.saveCustomPose(); return false;">Save Pose</a>
              <a href="#" class="Button" onclick="BOBLOX.Character.resetPose(); return false;">Undo Changes</a>
              <a href="#" class="Button" onclick="BOBLOX.Character.choosePose('stand'); return false;">Stand Up Straight</a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="Panel" id="WearingPanel">
      <h4>Currently Wearing</h4>
      <div class="PanelContent">
        <div id="WearingList"></div>
        <div style="clear:both"></div>
      </div>
    </div>
  </div>
  <div style="clear:both"></div>
</div>
<?php
page_footer();
