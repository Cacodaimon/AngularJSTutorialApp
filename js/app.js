angular.module('cacoBookMark.service', ['ngResource']).factory('Rest', function ($resource) {
    return $resource('api/bookmark/:id', {}, {
        query: {method: 'GET', isArray: true},
        get: {method: 'GET'},
        delete: {method: 'DELETE'},
        edit: {method: 'PUT'},
        add: {method: 'POST'}
    });
});

angular.module('cacoBookMark', ['cacoBookMark.service']).config(function ($httpProvider) {
    $httpProvider.defaults.transformRequest = function (data) {
        var str = [];
        for (var p in data) {
            data[p] != undefined && str.push(encodeURIComponent(p) + '=' + encodeURIComponent(data[p]));
        }
        return str.join('&');
    };
    $httpProvider.defaults.headers.put['Content-Type'] = $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
}).config(['$routeProvider', function ($routeProvider) {
        $routeProvider.
            when('/add', {templateUrl: 'partials/add.html', controller: BookMarkCtrl}).
            when('/edit/:id', {templateUrl: 'partials/edit.html', controller: BookMarkCtrl}).
            when('/', {templateUrl: 'partials/list.html', controller: BookMarkCtrl});
    }]);

var BookMarkCtrl = function ($scope, $routeParams, $location, Rest) {
    $scope.message = null;

    if ($routeParams.id) {
        $scope.bookmark = Rest.get({id: $routeParams.id});
    } 
    if ($location.path() == '/') {
        $scope.bookmarks = Rest.query();
    }

    $scope.add = function () {
        Rest.add({}, $scope.newBookMark, function (data) {
            $location.path('/');
        }, function () {
            $scope.message = "Error: URL not reachable?";
        });
    };

    $scope.delete = function (id) {
        if (!confirm('Confirm delete.')) {
            return;
        }

        Rest.delete({id: id}, {}, function (data) {
            $location.path('/');
        });
    };

    $scope.save = function () {
        Rest.edit({id: $scope.bookmark.id}, $scope.bookmark, function (data) {
            $location.path('/');
        });
    };
};