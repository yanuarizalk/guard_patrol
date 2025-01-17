var routes = [
    {path: "/", url: "./pages/register.html", options: {
        transition: 'f7-dive'
    }},
    {path: "/main", url: "./pages/main.html", options: {
        transition: 'f7-dive'
    }},
    {path: "/profile", url: "./pages/profile.html", options: {
        transition: 'f7-push'
    }},
    {path: "/check-in", url: "./pages/check-in.html", options: {
        transition: 'f7-push'
    }},
    {path: "/check_classic/:route/", url: "./pages/check_classic.html", options: {
        transition: 'f7-cover'
    }, name: "check_classic"},
    {path: "/check_common/:route/", url: "./pages/check_common.html", options: {
        transition: 'f7-cover'
    }, name: "check_common"},
    {path: "/map", url: "./pages/map.html", options: {
        transition: 'f7-cover-v'
    }},
    {path: "/history", url: "./pages/history.html", options: {
        transition: 'f7-push'
    }},
    {path: "/history_detail/:history_id/", url: "./pages/history_detail.html", 
        options: {
            transition: 'f7-push'
        },
        name: 'history_detail'
    },
    {path: "/manage", url: "./pages/manage.html", options: {
        transition: 'f7-push'
    }},
    {path: "/users", url: "./pages/users.html", options: {
        transition: 'f7-parallax'
    }},
    {path: "/checkpoints", url: "./pages/cp.html", options: {
        transition: 'f7-parallax'
    }}
];