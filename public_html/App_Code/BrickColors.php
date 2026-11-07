<?php
/* The 32 body colours, matching the columns of Asset/Figure/surfaces.png.
   Keep in sync with js/BrickColors.js */

function brick_colors()
{
    return array(
        array('White', '#f2f3f2'), array('Light stone grey', '#e5e4de'), array('Medium stone grey', '#a4a3a5'),
        array('Dark stone grey', '#646062'), array('Black', '#1c2b35'), array('Bright red', '#c4291c'),
        array('Bright yellow', '#f5cd30'), array('Cool yellow', '#fdea8d'), array('Bright blue', '#0e6aac'),
        array('Bright bluish green', '#01909c'), array('Medium blue', '#6f9ac9'), array('Pastel blue', '#81bcdb'),
        array('Light blue', '#b5d2e3'), array('Sand blue', '#75879d'), array('Bright orange', '#da8641'),
        array('Br. yellowish orange', '#e29c40'), array('Earth green', '#28472d'), array('Dark green', '#298047'),
        array('Bright green', '#4c984b'), array('Br. yellowish green', '#a5be47'), array('Medium green', '#a2c48c'),
        array('Sand green', '#799182'), array('Dark orange', '#a16035'), array('Reddish brown', '#6a4128'),
        array('Bright violet', '#6c337c'), array('Light reddish violet', '#e8bbc7'), array('Medium red', '#da877a'),
        array('Brick yellow', '#d7c59a'), array('Sand red', '#967a77'), array('Brown', '#7d5d46'),
        array('Nougat', '#cc8f69'), array('Light orange', '#eab992'),
    );
}

function brick_color_valid($id)
{
    return is_int($id) && $id >= 0 && $id < 32;
}

function brick_color_hex($id)
{
    $c = brick_colors();
    return isset($c[$id]) ? $c[$id][1] : '#a4a3a5';
}

/** Body parts: name used in JS => users table column */
function body_parts()
{
    return array(
        'Head' => 'color_head',
        'Torso' => 'color_torso',
        'LeftArm' => 'color_left_arm',
        'RightArm' => 'color_right_arm',
        'LeftLeg' => 'color_left_leg',
        'RightLeg' => 'color_right_leg',
    );
}

/** The classic 2006 look new accounts start with. */
function default_body_colors()
{
    return array('Head' => 6, 'Torso' => 8, 'LeftArm' => 6, 'RightArm' => 6, 'LeftLeg' => 19, 'RightLeg' => 19);
}
