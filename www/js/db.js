
var db;

async function init_db() {
    /*db = window.sqlitePlugin.openDatabase({
        name: "sgc.db", location: "default",
        androidDatabaseProvider: "system"
    });
    db.transaction(function(tx) {
        console.log(tx);
        tx.executeSql("CREATE TABLE IF NOT EXIST profile ( "+
            "id TEXT, dt_registered INTEGER"
        +" )");
    });*/
    console.log("Init DB");
    db = await new cordova.plugins.SecureStorage(function() {
        console.log("SS Success");
    },
        function(err) {
            console.log("SS Error: " + err);
        }, "sgc"
    );
}
//true, if user have been registered before
async function check_profile() {
    app.preloader.show();
    var result = await new Promise(function(res, rej) {
        db.get(function(val) {
            if (val == "") res(null);
            db.get(function(val2) {
                res([val, val2]);
            }, function(err) {
                console.log("SS Error on Get: " + err);
                res(null);
            }, "token");
        }, function(err) {
            console.log("SS Error on Get: " + err);
            res(null);
        }, "nrp");
    });
    if (result == null) {
        app.preloader.hide();
        return false;
    }
    
    var cb = await new Promise(function (res, rej) {
        app.request.post(API_SERVER + "check_profile", {
            nrp: result[0], token: result[1]
        }, function(data, status, xhr) {
            app.preloader.hide();
            if (data.status == "error") {
                res(false);
            } else if (data.status == "success") {
                res(true);
            }
        }, function(xhr, status) {
            app.preloader.hide();
            res(false);
            app.dialog.alert("Unable to connect to the server. Make sure your internet is usable & active", "Error", () => {
                check_profile();
            });
            console.log(xhr);
        }, "json");
    });
    
    return cb;
}

async function get_profile() {
    return await new Promise(function(res, rej) {
        db.get(function(val) {
            if (val == "") res(null);
            db.get(function(val2) {
                db.get(function(val3) {
                    res([val, val2, val3]);
                }, function(err) {
                    console.log("SS Error on Get: " + err);
                    res([val, val2]);
                }, "level");
            }, function(err) {
                console.log("SS Error on Get: " + err);
                res(null);
            }, "token");
        }, function(err) {
            console.log("SS Error on Get: " + err);
            res(null);
        }, "nrp");
    });
}

//document.addEventListener("deviceready", init_db, false);

