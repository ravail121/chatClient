<?php

function moderate_image_content($image) {

    $result = array();
    $result['success'] = true;

    if (!empty(Registry::load('settings')->image_moderation) && Registry::load('settings')->image_moderation !== 'disable') {

        $image_moderation = Registry::load('settings')->image_moderation;

        if ($image_moderation === 'enable') {
            $image_moderation = 'sight_engine';
        }

        if (isset($image) && !empty($image)) {

            if (isset($image_moderation) && !empty($image_moderation)) {
                $load_fn_file = 'fns/image_moderation/'.$image_moderation.'.php';
                if (file_exists($load_fn_file)) {
                    include($load_fn_file);
                }
            }
        }

    }
    return $result;
}