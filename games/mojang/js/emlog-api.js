/**
 * wx_mojang - Emlog登录状态检测
 */

const EmlogAPI = {
    /**
     * 检查登录状态
     */
    checkLogin() {
        return fetch('?rest-api=userinfo')
            .then(r => r.json())
            .then(d => {
                if (d && d.username) {
                    return { loggedIn: true, username: d.username, uid: d.uid };
                }
                return { loggedIn: false };
            })
            .catch(() => ({ loggedIn: false }));
    }
};
