angular
    .module('AngularAdmin', [])
    .controller('AdminController', AdminController)
    .factory("Request", RequestWrapper);

// wrapper for $http for future expansion
function RequestWrapper($http) {
    return {
        foreground: function () {
            if (arguments) {
                if (arguments[0]) {
                    arguments[0].loadType = 'foreground';
                }
            }
            return $http.apply($http, arguments);
        },
        background: function () {
            if (arguments) {
                if (arguments[0]) {
                    arguments[0].loadType = 'background';
                }
            }
            return $http.apply($http, arguments);
        },
        invisible: function () {
            if (arguments) {
                if (arguments[0]) {
                    arguments[0].loadType = 'invisible';
                }
            }
            return $http.apply($http, arguments);
        }
    };
}

AdminController.$inject = ['$window', 'Request'];

function AdminController($window, Request) {
    // assign ctrl variable to avoid scope issues with this
    var ctrl = this;
    ctrl.debug = 'If you can see this, then AdminController is working';
    ctrl.dataLoaded = false;
    ctrl.errors = [];

    if (typeof($window.apiName) != 'undefined') {
        ctrl.apiName = $window.apiName;
    } else {
        ctrl.errors.push({
            content: 'Could not find ajax api name.'
        });
    }

    if (typeof($window.ajaxurl) != 'undefined') {
        ctrl.ajaxUrl = $window.ajaxurl + '?action=' + ctrl.apiName;
    } else {
        ctrl.errors.push({
            content: 'Could not find ajax url. Check your WP has ajax enabled'
        });
    }

    // default data values
    ctrl.data = {
        my_value: 'default value'
    }

    ctrl.loadData = function () {
        Request.foreground({
            method: "post",
            url: ctrl.ajaxUrl,
            data: {
                command: 'load',
                adminData: ctrl.data
            }
        }).success(function (apiResponse, status) {
            ctrl.processMessages(apiResponse);
            ctrl.data = apiResponse.adminData;
            ctrl.dataLoaded = true;
        });
    }

    ctrl.saveData = function () {
        ctrl.dataLoaded = false;
        ctrl.notifications = [
            {
                content: 'Saving data please wait...'
            }
        ];
        Request.foreground({
            method: "post",
            url: ctrl.ajaxUrl,
            data: {
                command: 'save',
                adminData: ctrl.data
            }
        }).success(function (apiResponse, status) {
            ctrl.processMessages(apiResponse);
            ctrl.dataLoaded = true;
        });
    }

    ctrl.processMessages = function (apiResponse) {
        ctrl.notifications = [];
        ctrl.errors = [];
        if (typeof(apiResponse.messages) != 'undefined') {
            ctrl.notifications = apiResponse.messages;
        }
        if (typeof(apiResponse.errors) != 'undefined') {
            ctrl.errors = apiResponse.errors;
        }

    }

    ctrl.loadData();
}

