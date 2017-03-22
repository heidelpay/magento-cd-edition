/**
 * heidelpay payment frame javascript
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link  https://dev.heidelpay.de/magento
 *
 * @author  Jens Richter
 *
 * @package  Heidelpay
 * @subpackage Magento
 * @category Magento
 */

document.observe(
    'dom:loaded', function () {
        // Get the target origin from the FRONTEND.PAYMENT_FRAME_URL parameter
        var targetOrigin = getDomainFromUrl($('paymentFrame').readAttribute('src'));

        // ### Sending postMessages ###


        var paymentFrameForm = $('hcdForm');
        var paymentFrameIframe = $('paymentFrame');

        // Add an event listener that will execute the sendMessage() function
        // when the send button is clicked.
        if (paymentFrameForm.addEventListener) {  // W3C DOM
            paymentFrameForm.addEventListener('submit', sendMessage);
        } else if (paymentFrameForm.attachEvent) { // IE DOM
            paymentFrameForm.attachEvent('onsubmit', sendMessage);
        }

        // A function to handle sending messages.
        function sendMessage(e) 
        {
            // Prevent any default browser behaviour.
            $('button_hcd').toggle();
            $('heidelpay-please-wait').toggle();

            if (e.preventDefault) {
                e.preventDefault();
            } else {
                e.returnValue = false;
            }

            // save the form data in an object
            var data = {};

            /*
             for (var i = 0, len = paymentFrameForm.length; i < len; ++i) {
             var input = paymentFrameForm[i];
             if (input.name) {
             data[input.name] = input.value;
             }
             }
             */

            // Send a json message with the form data to the iFrame receiver window.
            paymentFrameIframe.contentWindow.postMessage(JSON.stringify(data), targetOrigin);
        }

        // ### Utils ###

        // extract protocol, domain and port from url
        function getDomainFromUrl(url) 
        {
            var arr = url.split("/");
            return arr[0] + "//" + arr[2];
        }


        // Setup an event listener that calls receiveMessage() when the window
        // receives a new MessageEvent.
        if (window.addEventListener) {  // W3C DOM
            window.addEventListener('message', receiveMessage);
        } else if (window.attachEvent) { // IE DOM
            window.attachEvent('onmessage', receiveMessage);
        }

        // ### Receiving postMessages ###

        function receiveMessage(e) 
        {

            // Check to make sure that this message came from the correct domain.
            if (e.origin !== targetOrigin) {
                return;
            }

            var recMsg = JSON.parse(e.data);
            if (recMsg["POST.VALIDATION"] == "NOK") {
                $('button_hcd').toggle();
                $('heidelpay-please-wait').toggle();
            }

        }

    }
);
