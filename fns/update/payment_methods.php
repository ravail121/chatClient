<?php

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';

if (role(['permissions' => ['super_privileges' => 'manage_payment_gateways']])) {

    include_once 'fns/filters/load.php';
    include_once 'fns/files/load.php';

    $noerror = true;
    $disabled = 0;
    $payment_gateway_id=null;
    $result['success'] = false;
    $result['error_message'] = Registry::load('strings')->invalid_value;
    $result['error_key'] = 'invalid_value';
    $result['error_variables'] = [];

    if (!isset($data['payment_method']) || empty($data['payment_method'])) {
        $result['error_variables'][] = ['payment_method'];
        $noerror = false;
    }

    if (isset($data['payment_gateway_id'])) {
        $payment_gateway_id = filter_var($data["payment_gateway_id"], FILTER_SANITIZE_NUMBER_INT);
    }

    if ($noerror && !empty($payment_gateway_id)) {

        $payment_methods = ['paypal', 'stripe'];

        if (!in_array($data['payment_method'], $payment_methods)) {
            $data['payment_method'] = 'paypal';
        }

        if (isset($data['disabled']) && $data['disabled'] === 'yes') {
            $disabled = 1;
        }

        $remove_fields = ['payment_method', 'disabled', 'add', 'update'];
        $credentials = sanitize_array($data);
        $credentials = array_diff_key($credentials, array_flip($remove_fields));
        $credentials = json_encode($credentials);


        DB::connect()->update("payment_gateways", [
            "identifier" => $data['payment_method'],
            "credentials" => $credentials,
            "disabled" => $disabled,
            "updated_on" => Registry::load('current_user')->time_stamp,
        ], ['payment_gateway_id' => $payment_gateway_id]);

        if (!DB::connect()->error) {

            $result = array();
            $result['success'] = true;
            $result['todo'] = 'reload';
            $result['reload'] = 'payment_methods';
        } else {
            $result['error_message'] = Registry::load('strings')->went_wrong;
            $result['error_key'] = 'something_went_wrong';
        }

    }
}

?>