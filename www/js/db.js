
async function check_profile() {
    app.preloader.show();
    var result = [localStorage.getItem("nrp"), localStorage.getItem("token")];
    if (result.find((el) => null) === null) {
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

function get_profile() {
    return [
        localStorage.getItem("nrp"),
        localStorage.getItem("token"),
        localStorage.getItem("level")
    ];
}

