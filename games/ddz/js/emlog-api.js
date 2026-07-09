const EmlogAPI = {
    async checkLogin() {
        try {
            const response = await fetch(`${EMLOG_CONFIG.baseUrl}/?rest-api=userinfo`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.code === 0 && data.data && data.data.userinfo) {
                return {
                    loggedIn: true,
                    user: {
                        uid: data.data.userinfo.uid,
                        nickname: data.data.userinfo.nickname,
                        avatar: data.data.userinfo.avatar || null,
                        role: data.data.userinfo.role,
                        email: data.data.userinfo.email
                    }
                };
            }
            return { loggedIn: false };
        } catch (error) {
            console.error('检查登录状态失败:', error);
            return { loggedIn: false, error: true };
        }
    }
};
