<?php

include_once 'fns/SleekDB/Store.php';

use SleekDB\Store;

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$no_error = true;
$order_id = null;

if (role(['permissions' => ['memberships' => 'edit_site_transactions']])) {

    if (isset($data["order_id"])) {
        $order_id = filter_var($data["order_id"], FILTER_SANITIZE_NUMBER_INT);
    }

    if (!empty($order_id) && isset($data['take_action']) && !empty($data['take_action'])) {

        $columns = [
            'membership_orders.order_id', 'membership_orders.user_id',
            'membership_orders.membership_package_id',
            'membership_orders.order_status', 'membership_packages.is_recurring',
            'membership_orders.created_on', 'membership_packages.related_site_role_id',
            'membership_packages.site_role_id_on_expire', 'membership_packages.duration',
        ];

        $join["[>]membership_packages"] = ['membership_orders.membership_package_id' => 'membership_package_id'];

        $where["membership_orders.order_id"] = $order_id;

        $where["LIMIT"] = 1;

        $order = DB::connect()->select('membership_orders', $join, $columns, $where);

        if (isset($order[0])) {
            $order = $order[0];

            if ($data['take_action'] === 'disapprove_unenroll') {
                if (!isset($data['site_role']) || empty($data['site_role'])) {
                    $no_error = false;
                    $result['error_message'] = Registry::load('strings')->invalid_value;
                    $result['error_key'] = 'invalid_value';
                    $result['error_variables'] = ['site_role'];
                } else {
                    $membership_logs = new Store('membership_logs', 'assets/nosql_database/'); 
                    $membership_logs->deleteById($order['user_id']);
                    DB::connect()->delete('site_users_membership', ['user_id' => $order['user_id']]);
                    DB::connect()->update('membership_orders', ['order_status' => 2], ['order_id' => $order_id]);
                    DB::connect()->update('site_users', ['site_role_id' => $data['site_role']], ['user_id' => $order['user_id']]);
                }
            } else if ($data['take_action'] === 'disapprove') {
                DB::connect()->update('membership_orders', ['order_status' => 2], ['order_id' => $order_id]);
            } else if ($data['take_action'] === 'approve') {
                DB::connect()->update('membership_orders', ['order_status' => 1], ['order_id' => $order_id]);
            } else if ($data['take_action'] === 'approve_enroll') {
                DB::connect()->update('membership_orders', ['order_status' => 1], ['order_id' => $order_id]);

                $non_expiring = 0;

                if (!empty($order['is_recurring'])) {
                    $non_expiring = 1;
                    $expiring_on = Registry::load('current_user')->time_stamp;
                } else {
                    $duration = 1;

                    if (!empty($order['duration'])) {
                        $duration = $order['duration'];
                    }

                    $expiring_on = Registry::load('current_user')->time_stamp;
                    $expiring_on = strtotime($expiring_on);
                    $expiring_on = strtotime('+'.$duration.' days', $expiring_on);
                    $expiring_on = date('Y-m-d H:i:s', $expiring_on);
                }

                $membership_data = [
                    'user_id' => $order['user_id'],
                    'membership_package_id' => $order['membership_package_id'],
                    'started_on' => Registry::load('current_user')->time_stamp,
                    'expiring_on' => $expiring_on,
                    'non_expiring' => $non_expiring,
                    'updated_on' => Registry::load('current_user')->time_stamp,
                ];

                $user_membership = DB::connect()->select('site_users_membership',
                    ['site_users_membership.membership_info_id'],
                    ['site_users_membership.user_id' => $order['user_id']]
                );

                if (isset($user_membership[0])) {
                    DB::connect()->update('site_users_membership', $membership_data,
                        ['site_users_membership.user_id' => $order['user_id']]);
                } else {
                    DB::connect()->insert('site_users_membership', $membership_data);
                }

                $membership_logs = new Store('membership_logs', 'assets/nosql_database/');
                $membership_data["_id"] = $order['user_id'];
                $membership_data["site_role_id_on_expire"] = $order['site_role_id_on_expire'];
                $membership_logs->updateOrInsert($membership_data, false);

                $related_site_role_id = $order['related_site_role_id'];

                DB::connect()->update('site_users', ['site_role_id' => $related_site_role_id],
                    ['site_users.user_id' => $order['user_id']]);
            }
        }
    }

    if ($no_error) {
        $result = array();
        $result['success'] = true;
        $result['todo'] = 'reload';
        $result['reload'] = ['transactions'];
    }

}
?>