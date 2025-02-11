/*!
 * Fresns (https://fresns.org)
 * Copyright 2021-Present Jevan Tang
 * Licensed under the Apache-2.0 license
 */

var FresnsCallback = {
    encode: function(callbackAction, apiData = null, apiCode = 0, apiMessage = 'ok') {
        const messageArr = {
            code: apiCode,
            message: apiMessage,
            data: apiData,
            action: callbackAction,
            // action: {
            //     postMessageKey: '',
            //     windowClose: true,
            //     redirectUrl: '',
            //     dataHandler: '',
            // },
        }

        const messageString = JSON.stringify(messageArr);

        return messageString;
    },

    decode: function(stringify = '') {
        const errorResponse = {
            code: 40000,
            message: 'Callback data format error',
            data: null,
            action: {
                postMessageKey: '',
                windowClose: true,
                redirectUrl: '',
                dataHandler: '',
            },
        }

        if (!stringify) {
            return errorResponse;
        }

        let callbackData;

        try {
            callbackData = JSON.parse(stringify);
        } catch (error) {
            return errorResponse;
        }

        if (!callbackData) {
            return errorResponse;
        }

        const successResponse = {
            code: callbackData.code !== undefined && callbackData.code !== null ? callbackData.code : errorResponse.code,
            message: callbackData.message || errorResponse.message,
            data: callbackData.data || errorResponse.data,
            action: {
                postMessageKey: callbackData.action?.postMessageKey || errorResponse.action.postMessageKey,
                windowClose: callbackData.action?.windowClose || errorResponse.action.windowClose,
                redirectUrl: callbackData.action?.redirectUrl || errorResponse.action.redirectUrl,
                dataHandler: callbackData.action?.dataHandler || errorResponse.action.dataHandler,
            },
        }

        console.log('FresnsCallback', 'Receive', successResponse);

        return successResponse;
    },

    send: function(callbackAction, apiData = null, apiCode = 0, apiMessage = 'ok', timeout = 0) {
        setTimeout(function () {
            const messageString = FresnsCallback.encode(callbackAction, apiData, apiCode, apiMessage);

            console.log('FresnsCallback', 'Send', {
                code: apiCode,
                message: apiMessage,
                action: callbackAction,
                data: apiData,
            });

            const userAgent = navigator.userAgent.toLowerCase();

            // Ask the application developer to add the following custom user agent information for Web View

            switch (true) {
                // iOS
                case (userAgent.indexOf('ios-webview') > -1):
                    // messageHandlerName = FresnsCallback
                    window.webkit.messageHandlers.FresnsCallback.postMessage(messageString);
                    break;

                // Android
                case (userAgent.indexOf('android-webview') > -1):
                    // messageHandlerName = FresnsCallback
                    window.FresnsCallback.receiveMessage(messageString);
                    break;

                // Flutter
                case (window.FlutterWebView):
                    // messageHandlerName = FlutterWebView
                    window.FlutterWebView.postMessage(messageString);
                    break;

                // React Native
                case (window.ReactNativeWebView):
                    // messageHandlerName = ReactNativeWebView
                    window.ReactNativeWebView.postMessage(messageString);
                    break;

                // Ionic
                case (userAgent.indexOf('ionic-webview') > -1):
                    // protocolName = FresnsCallback
                    window.location.href = 'FresnsCallback://message?data=' + encodeURIComponent(messageString);
                    break;

                // Cordova
                case (userAgent.indexOf('cordova-webview') > -1):
                    // protocolName = FresnsCallback
                    window.location.href = 'FresnsCallback://message?data=' + encodeURIComponent(messageString);
                    break;

                // WeChat Mini Program
                case (userAgent.indexOf('miniprogram') > -1):
                    loadScript('/static/js/sdk/jweixin.js?v=1.6.2', function() {
                        wx.miniProgram.postMessage({ data: messageString });
                        wx.miniProgram.navigateBack();
                    });
                    break;

                // UniApp
                case (userAgent.indexOf('uni-app') > -1):
                    loadScript('/static/js/sdk/uni.webview.js?v=1.5.5', function() {
                        uni.postMessage({ data: messageString });
                    });
                    break;

                // Web Browser
                default:
                    parent.postMessage(messageString, '*');
            }
        }, timeout);
    },
};

function loadScript(url, callback) {
    var script = document.createElement('script');
    script.type = 'text/javascript';

    if (script.readyState) { // IE
        script.onreadystatechange = function() {
            if (script.readyState == 'loaded' || script.readyState == 'complete') {
                script.onreadystatechange = null;
                callback();
            }
        };
    } else { // Other Browsers
        script.onload = function() {
            callback();
        };
    }

    script.src = url;
    document.getElementsByTagName('head')[0].appendChild(script);
}
