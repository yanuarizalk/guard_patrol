var API_SERVER = "https://sgcp.tyfountex.com/";
var DEFAULT_LOCATION = {
    lat: -7.564901266365109, lng: 110.7632319664257
};
var app = new Framework7({
    root: "#app", name: "Security Guard Checkpoint",
    id: "com.tyfountex.sgc", panel: {
        swipe: "left"
    }, routes: routes
});
var $$ = Dom7;

app.views.create('#view-main', {
    url: "/",
    main: true
});

document.addEventListener('deviceready', onDeviceReady, false);

async function onDeviceReady() {
    console.log("I am ready!");
    await init_db();
    if (await check_profile()) {
        app.views.main.router.navigate("/main", {
            reloadAll: true, clearPreviousHistory: true
        });
    }
}
