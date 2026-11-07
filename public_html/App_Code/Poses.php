<?php
/*
 * Character poses.
 *
 * A pose is a set of joint angles in degrees:
 *   Head:              yaw (turn, + = to the figure's left), pitch (nod, + = look down), roll (tilt)
 *   RightArm/LeftArm:  swing (+ = forward), raise (+ = out to the side / up)
 *   RightLeg/LeftLeg:  swing (+ = forward), raise (+ = out to the side)
 *
 * Saved per user as JSON in users.pose: {"name": "wave", "joints": {...}}.
 * Avatar3D.js turns the angles into rotations around the neck, shoulders and hips.
 */

/** joint => axis => array(min, max) */
function pose_limits()
{
    return array(
        'Head' => array('yaw' => array(-70, 70), 'pitch' => array(-35, 35), 'roll' => array(-30, 30)),
        'RightArm' => array('swing' => array(-180, 180), 'raise' => array(-20, 180)),
        'LeftArm' => array('swing' => array(-180, 180), 'raise' => array(-20, 180)),
        'RightLeg' => array('swing' => array(-110, 110), 'raise' => array(-15, 60)),
        'LeftLeg' => array('swing' => array(-110, 110), 'raise' => array(-15, 60)),
    );
}

/** Preset poses: key => array(label, joints). Missing angles are 0. */
function pose_presets()
{
    return array(
        'stand' => array('Standing', array()),
        'wave' => array('Waving', array(
            'Head' => array('yaw' => -10, 'roll' => -8),
            'RightArm' => array('raise' => 150, 'swing' => 10),
        )),
        'walk' => array('Walking', array(
            'RightArm' => array('swing' => 35), 'LeftArm' => array('swing' => -35),
            'RightLeg' => array('swing' => -30), 'LeftLeg' => array('swing' => 30),
        )),
        'run' => array('Running', array(
            'Head' => array('pitch' => -5),
            'RightArm' => array('swing' => -70), 'LeftArm' => array('swing' => 70),
            'RightLeg' => array('swing' => 50), 'LeftLeg' => array('swing' => -45),
        )),
        'cheer' => array('Cheering', array(
            'Head' => array('pitch' => -15),
            'RightArm' => array('raise' => 155), 'LeftArm' => array('raise' => 155),
        )),
        'tpose' => array('T-Pose', array(
            'RightArm' => array('raise' => 90), 'LeftArm' => array('raise' => 90),
        )),
        'zombie' => array('Zombie', array(
            'Head' => array('roll' => 12, 'pitch' => 8),
            'RightArm' => array('swing' => 90), 'LeftArm' => array('swing' => 85),
            'LeftLeg' => array('swing' => 12),
        )),
        'point' => array('Pointing', array(
            'Head' => array('yaw' => -20),
            'RightArm' => array('swing' => 90, 'raise' => 25),
        )),
        'sit' => array('Sitting', array(
            'RightArm' => array('swing' => 35, 'raise' => 5), 'LeftArm' => array('swing' => 35, 'raise' => 5),
            'RightLeg' => array('swing' => 90), 'LeftLeg' => array('swing' => 90),
        )),
        'kick' => array('Kicking', array(
            'Head' => array('pitch' => 10),
            'RightArm' => array('raise' => 40, 'swing' => -20), 'LeftArm' => array('raise' => 40, 'swing' => 20),
            'RightLeg' => array('swing' => 85),
        )),
        'think' => array('Thinking', array(
            'Head' => array('pitch' => 14, 'roll' => 12, 'yaw' => 10),
            'RightArm' => array('swing' => 80, 'raise' => -20),
            'LeftArm' => array('swing' => 30, 'raise' => -15),
        )),
        'dance' => array('Dancing', array(
            'Head' => array('roll' => -12),
            'RightArm' => array('raise' => 135, 'swing' => 15), 'LeftArm' => array('raise' => 50, 'swing' => 30),
            'LeftLeg' => array('raise' => 25),
        )),
        'hero' => array('Superhero', array(
            'Head' => array('pitch' => -10),
            'RightArm' => array('swing' => 175), 'LeftArm' => array('raise' => 15),
            'RightLeg' => array('swing' => -15), 'LeftLeg' => array('swing' => 25),
        )),
        'shrug' => array('Shrugging', array(
            'Head' => array('roll' => 10),
            'RightArm' => array('raise' => 45, 'swing' => 40), 'LeftArm' => array('raise' => 45, 'swing' => 40),
        )),
    );
}

/** Joint angles with every axis present (0 when missing), clamped to the limits. */
function pose_normalize_joints($joints)
{
    $out = array();
    foreach (pose_limits() as $joint => $axes) {
        foreach ($axes as $axis => $range) {
            $v = isset($joints[$joint][$axis]) && is_numeric($joints[$joint][$axis]) ? (float)$joints[$joint][$axis] : 0.0;
            $out[$joint][$axis] = round(max($range[0], min($range[1], $v)), 1);
        }
    }
    return $out;
}

/** The user's pose: array('name' => ..., 'label' => ..., 'joints' => ...) */
function pose_of_user($user)
{
    $presets = pose_presets();
    $data = isset($user['pose']) && $user['pose'] !== '' ? json_decode($user['pose'], true) : null;
    if (!is_array($data) || !isset($data['name'])) {
        $data = array('name' => 'stand', 'joints' => array());
    }
    $name = isset($presets[$data['name']]) || $data['name'] === 'custom' ? $data['name'] : 'stand';
    $joints = $name === 'custom' ? (isset($data['joints']) ? $data['joints'] : array()) : $presets[$name][1];
    return array(
        'name' => $name,
        'label' => $name === 'custom' ? 'Custom' : $presets[$name][0],
        'joints' => pose_normalize_joints($joints),
    );
}

/** Saves a preset ('wave') or a custom pose ('custom' + joints). Returns null or an error. */
function set_pose($user, $name, $joints)
{
    $presets = pose_presets();
    if ($name !== 'custom' && !isset($presets[$name])) return 'Unknown pose.';
    if ($name === 'custom' && !is_array($joints)) return 'Missing joint angles.';
    $data = $name === 'custom'
        ? array('name' => 'custom', 'joints' => pose_normalize_joints($joints))
        : array('name' => $name);
    db_query('UPDATE users SET pose = ? WHERE id = ?', array(json_encode($data), (int)$user['id']));
    avatar_invalidate($user['id']);
    return null;
}

/** Everything the character page needs to show the pose picker. */
function pose_client_data()
{
    $presets = array();
    foreach (pose_presets() as $key => $p) {
        $presets[] = array('name' => $key, 'label' => $p[0], 'joints' => pose_normalize_joints($p[1]));
    }
    return array('presets' => $presets, 'limits' => pose_limits());
}
