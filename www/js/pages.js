var viewer;
var areas = [], cp_marker = [], lines = [];

$$(document).on("page:mounted", ".page", async function(ev, page) {
    switch (page.name) {
        case "register": {
            new Cleave("#register input[name='nrp']", {
                delimiters: ['-'],
                blocks: [6,1],
                numericOnly: true
            });
            $$("#register")[0].addEventListener("submit", function(ev) {
                ev.preventDefault();
                app.preloader.show();
                var nrp = $$("#register input[name=\"nrp\"]")[0].value.replace("-","");
                app.request.post(API_SERVER + "register", {
                    nrp: nrp
                }, function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        localStorage.setItem("token", data.token);
                        localStorage.setItem("nrp", nrp);
                        localStorage.setItem("level", data.level);
                        localStorage.setItem("name", data.name);
                        app.views.main.router.navigate("/main");
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    console.log(xhr);
                }, "json");
            });
            break;
        }
        case "main": {
            var account = get_profile();
            if (account[2] == 0) {
                $$('.menu-container[data-name="history"]').remove();
                $$('.menu-container[data-name="manage"]').remove();
            } else if (account[2] == 1) {
                $$('.menu-container[data-name="check-in"]').remove();
                $$('.menu-container[data-name="manage"]').remove();
            }
            $$(".menu-container").on("click", function(ev) {
                if ($$(this).data("name") == "profile") {
                    var acc = get_profile();
                    app.dialog.alert(
                        "Name: " + acc[3] + "<br/>" + "NRP: " + acc[0],
                    "Profile");
                    return;
                }
                app.views.main.router.navigate("/" + $$(this).data("name"));
            });
            break;
        }
        case "check-in": {
            function SelectRoute(id, byCheckpoint = false) {
                $$('#cp-list .list-group').forEach((el) => {
                    if (byCheckpoint) {
                        if ($$(el).find('li[data-id="'+ id +'"]').length > 0) $$(el).addClass("active");
                        else $$(el).removeClass("active");
                    } else {
                        if ($$(el).data('route') == id) $$(el).addClass("active");
                        else $$(el).removeClass("active");
                    }
                });
            }
            async function PromptRoute() {
                return await new Promise((res, rej) => {
                    var routes = [];
                    $$('#cp-list .list-group').forEach((el) => {
                        routes.push({
                            text: $$(el).data('route'),
                            onClick: () => {
                                res($$(el).data('route'));
                            }
                        })
                    });
                    app.dialog.create({
                        title: 'Select Area',
                        text: 'Select which area to take in',
                        buttons: routes,
                        verticalButtons: true,
                        closeByBackdropClick: true,
                        on: {
                            closed: (a) => {
                                res(false);
                            }
                        }
                    }).open();
                });
            }
            async function check_cp() {
                if ($$(app.views.main.router.currentPageEl).data('name') != "check-in") return;
                var account = get_profile(), passed = [];
                await new Promise((res, rej) => {
                    app.request.post(API_SERVER + "history/user", {
                        account: {
                            nrp: account[0], token: account[1]
                        }
                    }, 
                    function(data, status, xhr) {
                        if (data.status == "error") {
                            app.dialog.alert(data.desc, "Error"); 
                            res(passed); return;
                        } else if (data.status == "success") {
                            $$('#cp-list ul > li').each(function(index,el) {
                                if (data.passed_cp.find(arr => arr.cp_id == $$(el).data('id')) == null)
                                    $$('#cp-list ul > li[data-id="'+ $$(el).data('id') +'"] .item-side i').removeClass('active');
                                else {
                                    $$('#cp-list ul > li[data-id="'+ $$(el).data('id') +'"] .item-side i').addClass('active');
                                    passed.push($$(el).data('id'));
                                }
                            });
                            res(passed);
                        }
                    }, function(xhr, status) {
                        console.log(xhr);
                        res(passed);
                    }, "json");
                });
                if (passed.length > 0)
                    SelectRoute(passed[0], true);
                else {
                    var route = await PromptRoute();
                    if (route !== false)
                        SelectRoute(route);
                }
            }
            $$(document).on("page:reinit", ".page[data-name=\"check-in\"]", async function(ev, page) {
                check_cp();
            });

            app.preloader.show();
            app.request.post(API_SERVER + "checkpoint", null, 
            function(data, status, xhr) {
                app.preloader.hide();
                if (data.status == "error") {
                    app.dialog.alert(data.desc, "Error"); return;
                } else if (data.status == "success") {
                    var amount = data.amount;
                    for (var gIndex in data.checkpoints) {
                        if (gIndex == "") continue;
                        if (data.checkpoints[gIndex].find((el) => {
                            return el.active == 1
                        }) == null) continue;
                        $$('#cp-list').append(
                            '<div class="list-group" data-route="'+ gIndex +'"><ul>' +
                                '<li class="list-group-title">Area '+ gIndex +'</li>'
                        );
                        for (var cpIndex in data.checkpoints[gIndex]) {
                            var cp = data.checkpoints[gIndex][cpIndex];
                            if (cp.active != 1) continue;
                            var html = 
                            '<li data-id="'+ cp.id +'">' +
                                '<a href="#" class="item-content item-link">' +
                                    '<div class="item-inner">' +
                                        '<div class="item-title">'+ cp.name +'</div>' +
                                    '</div>' +
                                    '<div class="item-side">' +
                                        '<i class="f7-icons">flag_fill</i>' +
                                    '</div>' +
                                '</a>' +
                            '</li>';
                            $$('#cp-list > .list-group:last-child > ul').append(html);
                        }
                        $$('#cp-list').append(
                            '</ul></div>'
                        );
                    }
                }
            }, function(xhr, status) {
                app.preloader.hide();
                app.dialog.alert("Error while fetching data to the server.", "Unexpected Server Error");
                console.log(xhr);
            }, "json");

            app.request.post(API_SERVER + "use_method", null, 
            function(data, status, xhr) {
                if (data.status == "error") {
                    app.dialog.alert(data.desc, "Error"); return;
                } else if (data.status == "success") {
                    switch (data.method.toUpperCase()) {
                        case "COMMON": {
                            $$("#check").data("method", data.method.toUpperCase());
                            $$("#check").html("Scan QR & Check In");
                            break;
                        }
                        case "CLASSIC":
                        default: {
                            $$("#check").data("method", "CLASSIC");
                            $$("#check").html("Take photo & Check In");
                            break;
                        }
                    }
                }
            }, function(xhr, status) {
                console.log(xhr);
            }, "json");

            await check_cp();
            var selected_route = () => {
                return $$('#cp-list .list-group.active').data("route");
            };

            $$("#show-map").on("click", function(ev) {
                app.views.main.router.navigate("/map");
            });
            $$("#check").on("click", async function(ev) {
                var route = selected_route();
                app.views.main.router.navigate({
                    name: $$("#check").data("method").toUpperCase() == "COMMON" ? "check_common" : "check_classic",
                    params: {
                        route: route
                    }
                });
            });
            $$("#restart").on("click", async function() {
                var route = selected_route();

                if ($$('#cp-list > .list-group[data-route="'+ route +'"] li .item-side i.active').length < 1) {
                    app.dialog.alert("You haven't passed 1 checkpoint yet", "Error");
                    return;
                }
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure, want to start from zero again?<br><i style='font-size:12px;'>This action can't be undone & your history checkpoint for today will be removed permanently</i>", "Restart Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var account = get_profile();
                app.request.post(API_SERVER + "history/restart", {
                    account: {
                        nrp: account[0], token: account[1]
                    }, time: "all", route: route
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        app.dialog.alert("Your patrol history have been restarted!", "Checkpoint Restarted");
                        check_cp();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                    console.log(xhr);
                }, "json");
            });
            $$("#revert").on("click", async function() {
                var route = selected_route();

                if ($$('#cp-list > .list-group[data-route="'+ route +'"] li .item-side i.active').length < 1) {
                    app.dialog.alert("You haven't passed 1 checkpoint yet", "Error");
                    return;
                }
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure, want to revert back to the last checkpoint?<br><i style='font-size:12px;'>This action can't be undone & your history of last checkpoint will be removed permanently</i>", "Revert Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var account = get_profile();
                app.request.post(API_SERVER + "history/restart", {
                    account: {
                        nrp: account[0], token: account[1]
                    }, time: "last", route: route
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        app.dialog.alert("Your last checkpoint have been reverted!", "Checkpoint Reverted");
                        check_cp();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                    console.log(xhr);
                }, "json");
            });
            break;
        }
        case "map": {
            var markers = [];
            var map = await init_vmap("map", {
                controls: {
                    myLocationButton: true,
                    myLocation: true
                }
            });
            var coor = DEFAULT_LOCATION;
            if (page.route.query.lat != null && page.route.query.lng != null) {
                coor = {
                    lat: page.route.query.lat, lng: page.route.query.lng
                };
                if (page.route.query.no_marker != "1") {
                    var marker = map.addMarker({
                        title: "Security position",
                        position: coor,
                        icon: "yellow"
                    });
                    markers.push(marker);
                }

                if (page.route.query.dir != null) {
                    var dirCoor = cp_marker.find((el) => el.name == page.route.query.dir).points;
                    var polyline = map.addPolyline({
                        points: [
                            coor, dirCoor
                        ]
                    });
                    var distance = plugin.google.maps.geometry.spherical.computeDistanceBetween(coor, dirCoor);
                    $$('#distance').addClass("active");
                    $$('#distance').html("Estimated Distance: " + distance.toFixed() + " meter");
                    /*polyline.on(plugin.google.maps.event.POLYLINE_CLICK, () => {
                        app.dialog.alert("Distance between security position & area location: " + distance + " (m)", "Distance Measurement");
                    });*/
                    lines.push(polyline);
                }
            }
            map.animateCamera({
                target: coor,
                zoom: 18,
                tilt: 90,
                bearing: 0,
                duration: 3000
            });
            /*map.on(plugin.google.maps.event.MY_LOCATION_CLICK, (loc) => {
                if (localStorage.getItem("level") == 1) return;
                
            });*/

            $$('#select_type').on('click', function() {
                app.dialog.create({
                    title: 'Select Map Type',
                    text: 'Choose which view to map',
                    buttons: [
                        { text: 'Satellite', onClick: function() {
                            map.setMapTypeId(plugin.google.maps.MapTypeId.SATELLITE);
                        }},
                        { text: 'Roadmap', onClick: function() {
                            map.setMapTypeId(plugin.google.maps.MapTypeId.ROADMAP);
                        }},
                        { text: 'Hybrid', onClick: function() {
                            map.setMapTypeId(plugin.google.maps.MapTypeId.HYBRID);
                        }},
                        { text: 'Terrain', onClick: function() {
                            map.setMapTypeId(plugin.google.maps.MapTypeId.TERRAIN);
                        }}
                    ],
                    verticalButtons: true,
                    closeByBackdropClick: true
                }).open();
            });
            break;
        }
        case "check_classic": {
            var area_pic = null; var source = 1;
            app.dialog.create({
                title: 'Source',
                text: 'Select a source which the picture is taken from',
                buttons: [
                    { text: 'Camera', onClick: function() {
                        source = Camera.PictureSourceType.CAMERA;
                    }},
                    { text: 'Existing file', onClick: function() {
                        source = Camera.PictureSourceType.PHOTOLIBRARY;
                    }}
                ],
                verticalButtons: true,
                closeByBackdropClick: false,
                on: {
                    closed: function() {
                        navigator.camera.getPicture(function(data) {
                            $$("#taken-picture img")[0].src = "data:image/jpeg;base64," + data;
                            area_pic = data;
                            $$(".navbar .title").html("Check - In");
                        }, function(err){
                            if (err == "No Image Selected") {
                                app.views.main.router.back();
                                return;
                            }
                            app.dialog.alert(err, "Error");
                        }, {
                            saveToPhotoAlbum: false, destinationType: 0, correctOrrientation: true,
                            sourceType: source
                        });
                    }
                }
            }).open();

            
            $$("#submit").on("click", async function(ev) {
                if (area_pic == null) return;
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure, want to send it now?", "Check-In Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var coor = await new Promise(function(res, rej) {
                    plugin.google.maps.LocationService.getMyLocation({
                        enableHighAccuracy: true
                    }, function(loc) {
                        res(loc);
                    }, function(err) {
                        res(err);
                    });
                });
                if (coor.status === false) {
                    app.preloader.hide();
                    app.dialog.alert(coor.error_message, "Error");
                    return;
                }
                var account = get_profile();
                app.request.post(API_SERVER + "checkin", {
                    image: area_pic, coor: coor.latLng, account: {
                        nrp: account[0], token: account[1]
                    }, remark: $$('#remark').val(),
                    route: page.route.params.route
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        app.dialog.alert(data.desc, "Checkpoint Reached");
                        app.views.main.router.back();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                    console.log(xhr);
                }, "json");
            });
            $$('#location').click(() => {
                app.views.main.router.navigate("/map");
            });
            break;
        }
        case "check_common": {
            var account = get_profile();
            var region = null;
            init_vmap();
            window.plugins.zxingPlugin.scan({
                beep_enabled: true,
                orientation_locked: false,
                prompt_message: 'Scan QR location',
                camera_id: 0,
                barcode_formats: [
                    'QR_CODE'
                ]
            }, function(text) {
                app.dialog.preloader("Checking...");
                app.request.post(API_SERVER + "checkpoint/validate", {
                    text: text, account: {
                        nrp: account[0], token: account[1]
                    },
                    route: page.route.params.route
                }, 
                function(data, status, xhr) {
                    app.dialog.close();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error");
                        app.views.main.router.back();
                        return;
                    } else if (data.status == "success") {
                        $$('#cur_cp').html(data.name);
                    }
                }, function(xhr, status) {
                    app.dialog.close();
                    app.dialog.alert("An error occured while validating data to the server", "Unexpected Server Error");
                    console.log(xhr);
                    app.views.main.router.back();
                }, "json");
                $$(".navbar .title").html("Check - In");
            }, function(err) { 
                if (err != "cancelled") {
                    app.dialog.alert(err, "Error");
                    console.log(err); 
                }
                app.views.main.router.back();
            });
            
            $$("#submit").on("click", async function(ev) {
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure, want to send it now?", "Check-In Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var coor = await new Promise(function(res, rej) {
                    plugin.google.maps.LocationService.getMyLocation({
                        enableHighAccuracy: true
                    }, function(loc) {
                        res(loc);
                    }, function(err) {
                        res(err);
                    });
                });
                if (coor.status === false) {
                    app.preloader.hide();
                    app.dialog.alert(coor.error_message, "Error");
                    return;
                }

                var isMatch = within_areas(coor.latLng, $$('#cur_cp').html());//plugin.google.maps.geometry.poly.containsLocation(coor.latLng, region);
                if (!isMatch){
                    app.preloader.hide();
                    app.dialog.alert("Your location isn't valid with current checkpoint location.", "Error");
                    return;
                }

                app.request.post(API_SERVER + "checkin", {
                    image: '', coor: coor.latLng, account: {
                        nrp: account[0], token: account[1]
                    }, remark: $$('#remark').val(),
                    route: page.route.params.route
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        app.dialog.alert(data.desc, "Checkpoint Reached");
                        app.views.main.router.back();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                    console.log(xhr);
                }, "json");
            });
            $$('#location').click(() => {
                app.views.main.router.navigate("/map");
            });
            break;
        }
        case "history": {
            await init_vmap();
            var account = get_profile();
            var filter_calender = app.calendar.create({
                backdrop: true,
                closeByBackdropClick: true,
                sheetSwipeToClose: true
            }), filter_date = new Date().setHours(0,0,0,0), filter_search = "";
            $$('#date').html(new Date(filter_date).toLocaleDateString('en-US', {day: 'numeric', month: 'short', year: 'numeric'} ) );
            $('#viewer').on('processing.dt', function(ev, settings, proc) {
                $('.dataTables_processing').css('display', 'none');
                if (proc) app.preloader.show();
                else app.preloader.hide();
            });

            viewer = $('#viewer').DataTable({
                serverSide: true,
                ajax: {
                    url: API_SERVER + 'history/dt', type: 'POST',
                    data: function(data) {
                        data.account = {
                            nrp: account[0], token: account[1]
                        };
                        data.date = filter_date / 1000;
                        data.find = filter_search;
                    }
                }, 
                responsive: true,
                paging: false, processing: true,
                searching: false,
                columns: [
                    {data: 'user_name'},
                    {data: 'checkpoint_name'},
                    {data: 'time_taken', searchable: false}
                ], 
                createdRow: function(row, data, index) {
                    data = data.DT_RowData;
                    var is_there =  within_areas(data.coor, data.checkpoint_name);
                    if (is_there) $$(row).addClass('active');
                    else if (is_there == null) $$(row).addClass('error');
                },
                orderMulti: false, order: [[2, 'desc']],
                language: {
                    emptyTable: 'There is no checkpoint record on this date',
                    info: 'Showing _END_ entries',
                    infoEmpty: ''
                },
                rowGroup: {
                    dataSrc: 'route'
                }
            });
            filter_calender.on('change', function(cal) {
                filter_date = new Date(cal.value).valueOf();
                filter_search = '';
                filter_calender.close();
                viewer.ajax.reload();
                $$('#date').html(new Date(filter_date).toLocaleDateString('en-US', {day: 'numeric', month: 'short', year: 'numeric'} ) );
            });
            $$('#filter_date').on('click', function() {
                filter_calender.open();
            });
            $$('#filter_search').on('click', function() {
                app.dialog.prompt("Search by User name / Checkpoint name", "Search", function(val) {
                    filter_search = val;
                    viewer.ajax.reload();
                });
            });

            $(document).off('click', '#viewer tbody tr');
            $(document).on('click', '#viewer tbody tr', function() {
                if ($$(this).children('td').hasClass('dataTables_empty')) return;
                if ($$(this).hasClass('dtrg-group')) return;
                app.views.main.router.navigate({
                    name: 'history_detail',
                    params: {
                        history_id: parseInt($(this).data('history_id'))
                    }
                });
            });

            break;
        }
        case "history_detail": {
            app.preloader.show();
            var id = parseInt(page.route.params.history_id);
            var account = get_profile();
            $$('#taken-picture img')[0].src = API_SERVER + "img/history/" + id + ".jpeg";
            app.request.post(API_SERVER + "history/detail", {
                //method: "CLASSIC"
                id: id, account: {
                    nrp: account[0], token: account[1]
                }
            }, 
            function(data, status, xhr) {
                app.preloader.hide();
                if (data.status == "error") {
                    app.dialog.alert(data.desc, "Error"); return;
                } else if (data.status == "success") {
                    $$('#remark').html(data.remark);
                    $$('#on').html(data.on);
                    $$('#by').html(data.by);
                    $$('#location').data('lat', data.lat);
                    $$('#location').data('lng', data.lng);
                    $$('#location').data('dir', data.cp_name);
                }
            }, function(xhr, status) {
                app.preloader.hide();
                app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                console.log(xhr);
            }, "json");

            $$('#location').on('click', function() {
                var lat = $$(this).data('lat');
                var lng = $$(this).data('lng');
                var dir = $$(this).data('dir');
                app.views.main.router.navigate('/map/?use_ls=0&lat=' + lat + '&lng=' + lng + '&dir=' + dir);
            });
            break;
        }
        case "manage": {
            $$(".menu-container").on("click", async function(ev) {
                if ($$(this).data('name') == "mode") {
                    var account = get_profile();
                    var confirm = await new Promise(function(res, rej) {
                        app.dialog.confirm("Are you sure want to change it?<br><i>It will not be applied instantly & will be changed on the next day</i>", "Switch Mode Confirmation", 
                        function() { res(true); }, function() { res(false); });
                    });
                    if (!confirm) return;
                    app.request.post(API_SERVER + "settings/switch_mode", {
                        account: {
                            nrp: account[0], token: account[1]
                        }
                    }, 
                    function(data, status, xhr) {
                        app.preloader.hide();
                        if (data.status == "error") {
                            app.dialog.alert(data.desc, "Error"); return;
                        } else if (data.status == "success") {
                            app.dialog.alert("Switch mode will be changed at midnight, so make sure there is no check-in activity around that time", "Switch Mode Success");
                        }
                    }, function(xhr, status) {
                        app.preloader.hide();
                        app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                        console.log(xhr);
                    }, "json");
                    return;
                }
                app.views.main.router.navigate("/" + $$(this).data("name"));
            });
            break;
        }
        case "users": {
            var account = get_profile();
            var filter_search = "";

            $('#viewer').on('processing.dt', function(ev, settings, proc) {
                $('.dataTables_processing').css('display', 'none');
                if (proc) app.preloader.show();
                else app.preloader.hide();
            });
            var ed = app.dialog.create({
                content: $$('#editor-dialog').html(),
                buttons: [
                    {text: "Save", close: false, onClick: async function() {
                        app.input.validateInputs('#editor');
                        if ($$('#editor input.input-invalid').length > 0) return;

                        app.preloader.show();
                        var account = get_profile(), xtra = null;
                        if (ed.params.title.toLowerCase() == "edit user")
                            xtra = $('#editor').data('prev_nrp');
                        app.request.post(API_SERVER + "users/" + (xtra == null ? "new" : "edit"), {
                            account: {
                                nrp: account[0], token: account[1]
                            },
                            input: app.form.convertToData('#editor'),
                            previous_data: xtra
                        }, 
                        function(data, status, xhr) {
                            app.preloader.hide();
                            ed.close();
                            if (data.status == "error") {
                                app.dialog.alert(data.desc, "Error");
                            } else if (data.status == "success") {
                                $('#viewer').DataTable().ajax.reload();
                            }
                        }, function(xhr, status) {
                            app.preloader.hide();
                            ed.close();
                            app.dialog.alert('An error occured while sending data to the server', 'Error');
                            console.log(xhr);
                        }, "json");
                    }},
                    {text: "Cancel"}
                ],
                closeByBackdropClick: false,
                cssClass: 'dialog-nopad',
                on: {
                    opened: function() {
                        new Cleave(".dialog #editor input[name='nrp']", {
                            delimiters: ['-'],
                            blocks: [6,1],
                            numericOnly: true
                        });
                    }
                }
            });
            $$('#editor').remove();
            viewer = $('#viewer').DataTable({
                serverSide: true,
                ajax: {
                    url: API_SERVER + 'users/dt', type: 'POST',
                    data: function(data) {
                        data.account = {
                            nrp: account[0], token: account[1]
                        };
                        data.find = filter_search;
                    }
                }, 
                responsive: true,
                paging: false, processing: true,
                searching: false,
                columns: [
                    {data: 'nrp'},
                    {data: 'name'},
                    {data: 'level', searchable: false, render: function(data) {
                        return data == 0 ? "Security" : data == 1 ? "Manager" : data == 2 ? "Admin" : "Unkown";
                    }}
                ], 
                createdRow: function(row, data, index) {
                    data = data.DT_RowData;
                    if (data.is_used == 1) {
                        $$(row).addClass('active');
                    }
                },
                orderMulti: false, order: [[2, 'desc']],
                language: {
                    emptyTable: 'There is no user',
                    info: 'Showing _END_ entries',
                    infoEmpty: ''
                }
            });
            $$('#filter_search').on('click', function() {
                app.dialog.prompt("Search by NRP / User name", "Search", function(val) {
                    filter_search = val;
                    viewer.ajax.reload();
                });
            });
            $$('#unreg-user').on('click', async function() {
                app.popover.close();
                if ($('#ctx-user').data('is_used') == 0) {
                    app.dialog.alert('This user haven\'t been registered yet!', 'Error');
                    return;
                }
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure? want to unregister this one?<i>The current user who have been registered using it will be asked to register again</i>", "Unregister Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var account = get_profile();
                var user_data = $('#ctx-user').data();
                app.request.post(API_SERVER + "users/unreg", {
                    account: {
                        nrp: account[0], token: account[1]
                    },
                    nrp: user_data.nrp
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error");
                    } else if (data.status == "success") {
                        app.dialog.alert("User "+ user_data.name +" have been unregistered!", "Unregister Success");
                        $('#viewer').DataTable().ajax.reload();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert('An error occured while sending data to the server', 'Error');
                    console.log(xhr);
                }, "json");
            });
            $$('#new-user').on('click', function() {
                ed.setTitle('New User');
                ed.open();
                app.form.fillFromData('#editor', {nrp: '', name: '', level: '0'});
            });
            $$('#edit-user').on('click', function() {
                app.popover.close();
                var data = $('#ctx-user').data();
                ed.setTitle('Edit User');
                ed.open();
                app.form.fillFromData('#editor', {nrp: data.nrp, name: data.name, level: data.level});
                $('#editor').data('prev_nrp', data.nrp);
            });
            $$('#remove-user').on('click', async function() {
                app.popover.close();
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure you want to remove it permanently?", "Remove Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var account = get_profile();
                var user_data = $('#ctx-user').data();
                app.request.post(API_SERVER + "users/remove", {
                    account: {
                        nrp: account[0], token: account[1]
                    },
                    nrp: user_data.nrp
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error");
                    } else if (data.status == "success") {
                        app.dialog.alert("User "+ user_data.name +" have been removed!", "Removal Success");
                        $('#viewer').DataTable().ajax.reload();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert('An error occured while sending data to the server', 'Error');
                    console.log(xhr);
                }, "json");
            });
            $(document).off('click', '#viewer tbody tr');
            $(document).on('click', '#viewer tbody tr', function() {
                var index = $$(this)[0].rowIndex;
                app.popover.open("#ctx-user", "#viewer tbody tr:nth-child("+ index +") td:nth-child(3)", true);
                $('#ctx-user').data($(this).data());
            });

            break;
        }
        case "checkpoints": {
            var sort_state = 0;
            function get_cp() {
                app.preloader.show();
                $$('#cp-list *').remove();
                app.request.post(API_SERVER + "checkpoint", null, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        for (var gIndex in data.checkpoints) {
                            $$('#cp-list').append(
                                '<div class="list-group" data-route="'+ gIndex +'"><ul>' +
                                    '<li class="list-group-title no-sorting">Area '+ gIndex +'</li>'
                            );
                            for (var cpIndex in data.checkpoints[gIndex]) {
                                var cp = data.checkpoints[gIndex][cpIndex];
                                var html = 
                                //'<li data-id="'+ cp.id +'" data-name="'+ cp.name +'" data-route="'+ gIndex +'" data-state="'+ (cp.active == 1 ? "on" : "off") +'" class="item-content item-link">' +
                                '<li class="item-content item-link">' +
                                    '<div class="item-inner">' +
                                        '<div class="item-title">'+ cp.name +'</div>' +
                                    '</div>' +
                                    '<div class="item-side">' +
                                        '<i class="f7-icons '+ (cp.active == 1 ? 'active' : '') +'">circle_fill</i>' +
                                    '</div>' +
                                    '<div class="sortable-handler"></div>' +
                                '</li>';
                                $('#cp-list > .list-group:last-child > ul').append($(html).data({
                                    id: cp.id, name: cp.name, route: gIndex, state: [cp.active == 1 ? "on" : "off"]
                                }));
                            }
                            $$('#cp-list').append(
                                '</ul></div>'
                            );
                        }
                        
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert("Error while fetching data to the server.", "Unexpected Server Error");
                    console.log(xhr);
                }, "json");
            }
            get_cp();


            var ed = app.dialog.create({
                content: $$('#editor-dialog').html(),
                buttons: [
                    {text: "Save", close: false, onClick: async function() {
                        app.input.validateInputs('#editor');
                        if ($$('#editor input.input-invalid').length > 0) return;

                        app.preloader.show();
                        var account = get_profile(), xtra = null;
                        if (ed.params.title.toLowerCase() == "edit checkpoint")
                            xtra = $('#editor').data('prev_data');
                        app.request.post(API_SERVER + "checkpoint/" + (xtra == null ? "new" : "edit"), {
                            account: {
                                nrp: account[0], token: account[1]
                            },
                            input: app.form.convertToData('#editor'),
                            previous_data: xtra
                        }, 
                        function(data, status, xhr) {
                            app.preloader.hide();
                            ed.close();
                            if (data.status == "error") {
                                app.dialog.alert(data.desc, "Error");
                            } else if (data.status == "success") {
                                app.dialog.alert("Checkpoint have been successfully updated!", "Checkpoint Updated");
                                get_cp();
                            }
                        }, function(xhr, status) {
                            app.preloader.hide();
                            ed.close();
                            app.dialog.alert('An error occured while sending data to the server', 'Error');
                            console.log(xhr);
                        }, "json");
                    }},
                    {text: "Cancel"}
                ],
                closeByBackdropClick: false,
                cssClass: 'dialog-nopad',
                on: {
                    opened: () => {
                        var areas_name = [];
                        $$('#cp-list .list-group').forEach((el) => {
                            areas_name.push($(el).data('route'));
                        });
                        app.autocomplete.create({
                            inputEl: 'input[name="route"]',
                            openIn: 'dropdown',
                            source: (query, render) => {
                                render(areas_name.filter(el => el.toLowerCase().indexOf(query.toLowerCase()) >= 0));
                            }
                        });
                    }
                }
            });
            $$('#editor').remove();

            $$('#new-cp').on('click', function() {
                ed.setTitle('New Checkpoint');
                ed.open();
                app.form.fillFromData('#editor', {name: ''/*, desc: ''*/, route: '', state: "off"});
            });
            $$('#edit-cp').on('click', function() {
                app.popover.close();
                var data = $('#ctx-cp').data();
                ed.setTitle('Edit Checkpoint');
                ed.open();
                app.form.fillFromData('#editor', {name: data.name/*, desc: data.desc*/, route: data.route, state: data.state});
                $('#editor').data('prev_data', data.id);
            });
            $$('#remove-cp').on('click', async function() {
                app.popover.close();
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure you want to remove it permanently?", "Remove Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var account = get_profile();
                var cp_data = $('#ctx-cp').data();
                app.request.post(API_SERVER + "checkpoint/remove", {
                    account: {
                        nrp: account[0], token: account[1]
                    },
                    id: cp_data.id
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error");
                    } else if (data.status == "success") {
                        app.dialog.alert("Checkpoint "+ cp_data.name +" have been removed!", "Removal Success");
                        get_cp();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert('An error occured while sending data to the server', 'Error');
                    console.log(xhr);
                }, "json");
            });
            $(document).off('click', '#cp-list ul .item-content');
            $(document).on('click', '#cp-list ul .item-content', function() {
                if (sort_state == true) return;
                app.popover.open("#ctx-cp", $$(this), true);
                $('#ctx-cp').data($(this).data());
            });
            //====================

            $$('#map_editor-cp').click(() => {
                window.open(EDITOR_MAP, '_system');
            });
            $$('#order-cp').click(() => {
                app.sortable.enable('#cp-list');
            });
            $$('#order-save-cp').click(async () => {
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure, want to change the sequence of checkpoints with those order?", "Re-order Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                var orders = [];
                var i = 0;
                var account = get_profile();
                $$('#cp-list .list-group').forEach((elGroup) => {
                    i++; var seq = 1;
                    $$('#cp-list .list-group:nth-child('+ i +') li.item-content').forEach((el) => {
                        orders.push({
                            id: $(el).data('id'), //route: $$(elGroup).data('route') || '', 
                            seq: seq
                        });
                        seq++;
                    });
                });
                app.request.post(API_SERVER + "checkpoint/reorder", {
                    account: {
                        nrp: account[0], token: account[1]
                    },
                    checkpoints: orders
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error");
                    } else if (data.status == "success") {
                        //app.dialog.alert("", "Reorder Success");
                        app.sortable.disable('#cp-list');
                        get_cp();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert('An error occured while sending data to the server', 'Error');
                    console.log(xhr);
                }, "json");
            });
            $$('#order-cancel-cp').click(() => {
                app.sortable.disable('#cp-list');
                get_cp();
            });
            $$(document).on('sortable:enable', '#cp-list', () => {
                $$('#cp-list .item-content .item-side').addClass("none");
                $$('#mnu-primary').addClass('none');
                $$('#mnu-order').removeClass('none');
                sort_state = true;
            });
            $$(document).on('sortable:disable', '#cp-list', () => {
                $$('#cp-list .item-content .item-side').removeClass("none");
                $$('#mnu-order').addClass('none');
                $$('#mnu-primary').removeClass('none');
                sort_state = false;
            });
            break;
        }

        default: break;
    }
});

async function init_vmap(divMap = "vmap", options = null) {
    areas = [];
    var vmap = plugin.google.maps.Map.getMap($$('#' + divMap)[0], options);
    await new Promise((res, rej) => {
        vmap.addKmlOverlay({
            url: KML_MAP
        }, function(kml) {
            console.log(kml);
            if (kml == null) res(false);
            var cp_area_layer = kml.kmlData.getAt(0).filter(el => el.get("name") == "Checkpoints Area")[0];
            var cp_marker_layer = kml.kmlData.getAt(0).filter(el => el.get("name") == "Checkpoints Marker")[0];
            cp_area_layer.forEach((el) => {
                areas.push({
                    name: el.get('name').value,
                    points: el.get('points')
                });
            });
            cp_marker_layer.forEach((el) => {
                cp_marker.push({
                    name: el.get('name').value,
                    points: el.get('position')
                });
            });
            res(true);
        });
    });
    return vmap;
}
function within_areas(coor, areaName) {
    try {
        if (plugin.google.maps.geometry.poly.containsLocation(coor, areas.find((el) => {
            return el.name.toLowerCase() == areaName.toLowerCase();
        }).points)) return true;
        else return false;
    } catch(exc) {
        return null;
    }
}

$$('document').on("xhr.dt", "#viewer", (e, set, json, xhr) => {
    if (json.status.toLowerCase() == "error") {
        app.preloader.hide();
        app.dialog.alert(json.desc, "Error");
    }
});



$$(document).on("click", "#back", function() {
    app.views.main.router.back();
});