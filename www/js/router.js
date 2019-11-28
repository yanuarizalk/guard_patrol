var routes = [
    {
        path: "/", url: "./pages/register.html"
    },
    {
        path: "/main", url: "./pages/main.html"
    },
    {path: "/profile", url: "./pages/profile.html", options: {
        transition: 'f7-parallax'
    }},
    {path: "/check-in", url: "./pages/check-in.html", options: {
        transition: 'f7-push'
    }},
    {path: "/check_classic", url: "./pages/check_classic.html", options: {
        transition: 'f7-push'
    }},
    {path: "/check_common", url: "./pages/check_common.html", options: {
        transition: 'f7-push'
    }},
    {path: "/map", url: "./pages/map.html", options: {
        transition: 'f7-parallax'
    }},
    {path: "/history", url: "./pages/history.html", options: {
        transition: 'f7-parallax'
    }},
    {path: "/history_detail/:history_id/", url: "./pages/history_detail.html", 
        options: {
            transition: 'f7-push'
        },
        name: 'history_detail'
    },
    {path: "/manage", url: "./pages/manage.html", options: {
        transition: 'f7-parallax'
    }},
    {path: "/about", url: "./pages/about.html", options: {
        transition: 'f7-parallax'
    }}
];