<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This class contains a list of webservice functions related to the PayUnity payment gateway.
 *
 * @package    paygw_payunity
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_payunity\external;

use context_system;
use core_payment\helper;
use external_api;
use external_function_parameters;
use external_value;
use core_payment\helper as payment_helper;
use paygw_payunity\event\payment_completed;
use paygw_payunity\event\payment_error;
use paygw_payunity\event\payment_successful;
use paygw_payunity\payunity_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class transaction_complete extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'The item id in the context of the component area'),
            'orderid' => new external_value(PARAM_TEXT, 'The order id coming back from PayUnity'),
            'resourcePath' => new external_value(PARAM_TEXT, 'The order id coming back from PayUnity'),
        ]);
    }

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea
     * @param int $itemid An internal identifier that is used by the component
     * @param string $orderid PayUnity order ID
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid, string $orderid,
        string $resourcepath, int $userid = 0): array {
        global $USER, $DB, $CFG;
        $stringman = get_string_manager();

        if ($userid == 0) {
            $userid = $USER->id;
        }

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'orderid' => $orderid,
            'resourcePath' => $resourcepath
        ]);

        $config = (object)helper::get_gateway_configuration($component, $paymentarea, $itemid, 'payunity');
        $sandbox = $config->environment == 'sandbox';

        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('payunity');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $payunityhelper = new payunity_helper($config->clientid, $config->secret, $sandbox);
        $orderdetails = $payunityhelper->get_order_details($resourcepath);

        // If something went wrong with first check_status -> try again with our internal id.
        // If resourcepath is '' we are coming from transactionlist.
        if ($orderdetails || $resourcepath === '') {
            if ($orderdetails->results->code === '700.400.580' || $orderdetails->results->code === '200.300.404'
                || $resourcepath === '') {
                // In this case we try to use internal id.
                $payments = $payunityhelper->get_transaction_record($orderid);
                $orderdetails = $payments->payments[0];
                // Fallback for Fallback -> should never happen.
                if ($orderdetails->results->code === '700.400.580' || $orderdetails->results->code === '200.300.404') {
                    $payments = $payunityhelper->get_transaction_record_exetrnal_id($orderid);
                    $orderdetails = $payments->payments[0];
                }
            }
        }

        $success = false;
        $message = '';
        $successurl = helper::get_success_url($component, $paymentarea, $itemid)->__toString();
        $serverurl = $CFG->wwwroot;

        if ($orderdetails) {
            $status = '';
            $url = $serverurl;
            // SANDBOX OR PROD.
            if ($sandbox == true) {
                if ($orderdetails->result->code == '000.100.110') {
                    // Approved.
                    $status = 'success';
                    $message = get_string('payment_successful', 'paygw_payunity');
                } else {
                    // Not Approved.
                    $status = false;
                }
            } else {
                if ($orderdetails->result->code == '000.000.000') {
                    // Approved.
                    $status = 'success';
                    $message = get_string('payment_successful', 'paygw_payunity');
                } else {
                    // Not Approved.
                    $status = false;
                }
            }

            if ($status == 'success') {
                $url = $successurl;
                // Get item from response.
                $item['amount'] = $orderdetails->amount;
                $item['currency'] = $orderdetails->currency;
                if (is_null( $item['amount'])) {
                    $item['amount'] = $orderdetails->payments[0]->amount;
                    $item['currency'] = $orderdetails->payments[0]->currency;
                }

                if ($item['amount'] == $amount && $item['currency'] == $currency) {
                    $success = true;

                    try {
                        $paymentid = payment_helper::save_payment($payable->get_account_id(), $component, $paymentarea,
                            $itemid, (int) $userid, $amount, $currency, 'payunity');

                        // Store PayUnity extra information.
                        $record = new \stdClass();
                        $record->paymentid = $paymentid;
                        $record->pu_orderid = $orderid;

                        // Store Brand in DB.
                        if (get_string_manager()->string_exists($orderdetails->paymentBrand, 'paygw_payunity')) {
                            $record->paymentbrand = get_string($orderdetails->paymentBrand, 'paygw_payunity');
                        } else {
                            $record->paymentbrand = get_string('unknownbrand', 'paygw_payunity');
                        }

                        // Store original value.
                        $record->pboriginal = $orderdetails->paymentBrand;

                        $DB->insert_record('paygw_payunity', $record);

                        // Set status in open_orders to complete.
                        if ($existingrecord = $DB->get_record('paygw_payunity_openorders',
                         ['tid' => $orderdetails->merchantTransactionId])) {
                            $existingrecord->status = 3;
                            $DB->update_record('paygw_payunity_openorders', $existingrecord);

                            // We trigger the payment_completed event.
                            $context = context_system::instance();
                            $event = payment_completed::create([
                                'context' => $context,
                                'userid' => $USER->id,
                                'other' => [
                                    'orderid' => $orderdetails->merchantTransactionId
                                ]
                            ]);
                            $event->trigger();
                        }

                        // We trigger the payment_successful event.
                        $context = context_system::instance();
                        $event = payment_successful::create(array('context' => $context, 'other' => [
                            'message' => $message,
                            'orderid' => $orderid]));
                        $event->trigger();

                        // The order is delivered.
                        payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $userid);

                    } catch (\Exception $e) {
                        debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                        $success = false;
                        $message = get_string('internalerror', 'paygw_payunity');
                    }
                } else {
                    $success = false;
                    $message = get_string('amountmismatch', 'paygw_payunity');
                }

            } else {
                $success = false;
                $message = get_string('paymentnotcleared', 'paygw_payunity');
            }

        } else {
            // Could not capture authorization!
            $success = false;
            $message = get_string('cannotfetchorderdatails', 'paygw_payunity');
        }

        // If there is no success, we trigger this event.
        if (!$success) {
            // We trigger the payment_successful event.
            $context = context_system::instance();
            $event = payment_error::create(array('context' => $context, 'other' => [
                    'message' => $message,
                    'orderid' => $orderid,
                    'itemid' => $itemid,
                    'component' => $component,
                    'paymentarea' => $paymentarea]));
            $event->trigger();
        }

        return [
            'url' => $url,
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns() {
        return new external_function_parameters([
            'url' => new external_value(PARAM_URL, 'Redirect URL.'),
            'success' => new external_value(PARAM_BOOL, 'Whether everything was successful or not.'),
            'message' => new external_value(PARAM_RAW, 'Message (usually the error message).'),
        ]);
    }
}
