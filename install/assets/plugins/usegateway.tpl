//<?php
/**
 * Payment Usegateway
 *
 * Usegateway payments processing
 *
 * @category    plugin
 * @version     1.0.0
 * @author      Pathologic
 * @internal    @events OnRegisterPayments,OnManagerBeforeOrderRender,OnBeforeOrderSending
 * @internal    @properties &title=Title;text; &secret_key=Secret Key;text; &debug=Debug;list;No==0||Yes==1;0
 * @internal    @modx_category Commerce
 * @internal    @installset base
 */

return require MODX_BASE_PATH . 'assets/plugins/usegateway/plugin.usegateway.php';
