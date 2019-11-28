var viewer;

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
                    //data = JSON.parse(data);
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        db.set(function(key) {
                            console.log("SS Set token success");
                        }, function(err) {
                            console.log("SS Error on Set: " + err)
                        }, "token", data.token);
                        
                        db.set(function(key) {
                            console.log("SS Set nrp success");
                        }, function(err) {
                            console.log("SS Error on Set: " + err)
                        }, "nrp", nrp);

                        db.set(function(key) {}, function(err) {}, "level", data.level);
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
            }
            $$(".menu-container").on("click", function(ev) {
                app.views.main.router.navigate("/" + Dom7(this).data("name"));
            });
            break;
        }
        case "check-in": {
            app.preloader.show();
            app.request.post(API_SERVER + "checkpoint", null, 
            function(data, status, xhr) {
                app.preloader.hide();
                //data = JSON.parse(data);
                if (data.status == "error") {
                    app.dialog.alert(data.desc, "Error"); return;
                } else if (data.status == "success") {
                    var amount = data.amount;
                    for (var index in data.checkpoints) {
                        var cp = data.checkpoints[index];
                        var html = 
'<li data-id="'+ cp.id +'" data-lat="'+ cp.latitude +'" data-lng="'+ cp.longitude +'">' +
    '<a href="#" class="item-content item-link">' +
        '<div class="item-inner">' +
            '<div class="item-title">'+ cp.name +'</div>' +
        '</div>' +
        '<div class="item-side">' +
            '<i class="f7-icons">flag_fill</i>' +
        '</div>' +
    '</a>' +
'</li>';
                        $$('#cp-list > ul').append(html);
                    }
                }
            }, function(xhr, status) {
                app.preloader.hide();
                app.dialog.alert("Error while fetching data to the server.", "Unexpected Server Error");
                console.log(xhr);
            }, "json");

            app.request.post(API_SERVER + "use_method", null, 
            function(data, status, xhr) {
                //data = JSON.parse(data);
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

            init_today_cp();            

            $$("#show-map").on("click", function(ev) {
                app.views.main.router.navigate("/map");
            });
            $$("#check").on("click", function(ev) {
                switch ($$(this).data("method").toUpperCase()) {
                    case "COMMON": {
                        app.views.main.router.navigate("/check_common");
                        break;
                    }
                    case "CLASSIC":
                    default: {
                        app.views.main.router.navigate("/check_classic");
                        break;
                    }
                }
            });
            $$(document).on("click", "#cp-list > ul > li", function() {
                var lat = $$(this).data('lat');
                var lng = $$(this).data('lng');
                app.views.main.router.navigate('/map/?use_ls=1&lat=' + lat + '&lng=' + lng + "&no_marker=1");
            });
            $$("#restart").on("click", async function() {
                if ($$('#cp-list > ul > li .item-side i.active').length < 1) {
                    app.dialog.alert("You haven't passed 1 route yet");
                    return;
                }
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure, want to start from zero again?<br><i style='font-size:12px;'>This action can't be undone & your history checkpoint for today will be removed permanently</i>", "Restart Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var account = await get_profile();
                app.request.post(API_SERVER + "history/restart", {
                    account: {
                        nrp: account[0], token: account[1]
                    }, time: "all"
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        app.dialog.alert("Your patrol history for today have been restarted!", "Checkpoint Restarted");
                        init_today_cp();
                    }
                }, function(xhr, status) {
                    app.preloader.hide();
                    app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                    console.log(xhr);
                }, "json");
            });
            $$("#revert").on("click", async function() {
                if ($$('#cp-list > ul > li .item-side i.active').length < 1) {
                    app.dialog.alert("You haven't passed 1 route yet");
                    return;
                }
                var confirm = await new Promise(function(res, rej) {
                    app.dialog.confirm("Are you sure, want to revert back to the last checkpoint?<br><i style='font-size:12px;'>This action can't be undone & your history of last checkpoint will be removed permanently</i>", "Revert Confirmation", 
                    function() { res(true); }, function() { res(false); });
                });
                if (!confirm) return;
                app.preloader.show();
                var account = await get_profile();
                app.request.post(API_SERVER + "history/restart", {
                    account: {
                        nrp: account[0], token: account[1]
                    }, time: "last"
                }, 
                function(data, status, xhr) {
                    app.preloader.hide();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error"); return;
                    } else if (data.status == "success") {
                        app.dialog.alert("Your last checkpoint have been reverted!", "Checkpoint Reverted");
                        init_today_cp();
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
            var map = plugin.google.maps.Map.getMap(document.getElementById("map"));
            var coor, markers = [];
            if (page.route.query.use_ls == 1 || page.route.query.use_ls == null) {
                coor = await new Promise(function(res, rej) {
                    map.getMyLocation({enableHighAccuracy: true}, function(result) {res(result);}, function(err){res(err);});
                });
                if (coor.status === false) {
                    app.dialog.alert(coor.error_message, "Error");
                    coor = {latLng: DEFAULT_LOCATION};
                } else {
                    var marker = map.addMarker({
                        title: "You are here",
                        position: coor.latLng
                    });
                    markers.push(marker);
                    marker.showInfoWindow();
                }
            } else {
                coor = {latLng: DEFAULT_LOCATION};
            }
            if (page.route.query.lat != null && page.route.query.lng != null) {
                coor = {latLng: {
                    lat: page.route.query.lat, lng: page.route.query.lng
                }};
                if (page.route.query.no_marker != "1") {
                    var marker = map.addMarker({
                        title: "Security position",
                        position: coor.latLng,
                        icon: "yellow"
                    });
                    markers.push(marker);
                }
            }
            map.animateCamera({
                target: coor.latLng,
                zoom: 18,
                tilt: 90,
                bearing: 0,
                duration: 3000
            });
            app.request.post(API_SERVER + "regions", null, 
            function(data, status, xhr) {
                if (data.status == "error") {
                    app.dialog.alert(data.desc, "Error"); return;
                } else if (data.status == "success") {
                    for (var index in data.regions) {
                        var poly = map.addPolygon({
                            points: JSON.parse(data.regions[index].region), 
                            fillColor: data.regions[index].color,
                            strokeWidth: 0
                        });
                        var region_marker = map.addMarker({
                            title: data.regions[index].name,
                            position: {
                                lat: data.regions[index].latitude,
                                lng: data.regions[index].longitude
                            },
                            icon: data.regions[index].mark_icon,
                            snippet: data.regions[index].description
                        });
                    }
                }
            }, function(xhr, status) {
                console.log(xhr);
            }, "json");

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
            var area_pic = null;
            navigator.camera.getPicture(function(data) {
                $$("#taken-picture img")[0].src = "data:image/jpeg;base64," + data;
                area_pic = data;
                //$$("#taken-picture img")[0].src = data;
                //document.getElementById("pic").src = data;
                $$(".navbar .title").html("Check - In");
            }, function(err){
                if (err == "No Image Selected")
                    app.views.main.router.back();
                app.dialog.alert(err, "Error");
            }, {
                saveToPhotoAlbum: false, destinationType: 0
            });
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
                var account = await get_profile();
                app.request.post(API_SERVER + "checkin", {
                    //method: "CLASSIC"
                    image: area_pic, coor: coor.latLng, account: {
                        nrp: account[0], token: account[1]
                    }, remark: $$('#remark').val()
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
            break;
        }
        case "check_common": {
            var account = await get_profile();
            var region = null;
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
                    }
                }, 
                function(data, status, xhr) {
                    app.dialog.close();
                    if (data.status == "error") {
                        app.dialog.alert(data.desc, "Error");
                        app.views.main.router.back();
                        return;
                    } else if (data.status == "success") {
                        $$('#cur_cp').html(data.name);
                        region = JSON.parse(data.region);
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

                var isMatch = plugin.google.maps.geometry.poly.containsLocation(coor.latLng, region);
                if (!isMatch){
                    app.preloader.hide();
                    app.dialog.alert("Your location isn't valid with current checkpoint location.", "Error");
                    return;
                }

                app.request.post(API_SERVER + "checkin", {
                    image: '', coor: coor.latLng, account: {
                        nrp: account[0], token: account[1]
                    }, remark: $$('#remark').val()
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
            break;
        }
        case "history": {
            var account = await get_profile();
            var filter_calender = app.calendar.create(), filter_date = new Date().setHours(0,0,0,0), filter_search = "";

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
                    var isMatch = plugin.google.maps.geometry.poly.containsLocation(data.coor, JSON.parse(data.region));
                    if (isMatch)
                        $(row).addClass('active');
                },
                orderMulti: false, order: [[2, 'desc']],
                language: {
                    emptyTable: 'There is no checkpoint record on this date',
                    info: 'Showing _END_ entries',
                    infoEmpty: ''
                }
            });
            filter_calender.on('change', function(cal) {
                filter_date = new Date(cal.value).valueOf();
                filter_search = '';
                filter_calender.close();
                viewer.ajax.reload();
            });
            $$('#filter_date').on('click', function() {
                filter_calender.open();
            });
            $$('#filter_search').on('click', function() {
                app.dialog.prompt("Search by User name / Area name", "Search", function(val) {
                    filter_search = val;
                    viewer.ajax.reload();
                });
            });

            $$(document).on('click', '#viewer tbody tr', function() {
                console.log($$(this));
                if ($$(this).children('td').hasClass('dataTables_empty')) return;
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
            var account = await get_profile();
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
                }
            }, function(xhr, status) {
                app.preloader.hide();
                app.dialog.alert("An error occured while sending data to the server", "Unexpected Server Error");
                console.log(xhr);
            }, "json");

            $$('#location').on('click', function() {
                var lat = $$(this).data('lat');
                var lng = $$(this).data('lng');
                app.views.main.router.navigate('/map/?use_ls=0&lat=' + lat + '&lng=' + lng);
            });
            break;
        }
        default: break;
    }
    //console.log(page);
});

async function init_today_cp() {
    if ($$(app.views.main.router.currentPageEl).data('name') != "check-in") return;
    var account = await get_profile();
    //var today = new Date().setHours(0,0,0,0);
    app.request.post(API_SERVER + "history/today_checkin", {
        account: {
            nrp: account[0], token: account[1]
        }
    }, 
    function(data, status, xhr) {
        //data = JSON.parse(data);
        if (data.status == "error") {
            app.dialog.alert(data.desc, "Error"); return;
        } else if (data.status == "success") {
            $$('#cp-list > ul > li').each(function(index,el) {
                //console.log($$(el).data('id'));
                if (data.passed_cp.find(arr => arr.cp_id == $$(el).data('id')) == null)
                    $$('#cp-list > ul > li[data-id="'+ $$(el).data('id') +'"] .item-side i').removeClass('active');
                else
                    $$('#cp-list > ul > li[data-id="'+ $$(el).data('id') +'"] .item-side i').addClass('active');
            });
            /*for (var index in data.passed_cp) {
                $$('#cp-list > ul > li[data-id="'+ data.passed_cp[index].cp_id +'"] .item-side i').addClass('active');
            }*/
        }
    }, function(xhr, status) {
        console.log(xhr);
    }, "json");
}

$$(document).on("page:reinit", ".page[data-name=\"check-in\"]", async function(ev, page) {
    init_today_cp();
});

$$(document).on("click", "#back", function() {
    app.views.main.router.back({ignoreCache: false});
});
