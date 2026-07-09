const Leaderboard = {
    // AJAX 接口基础URL（由 PHP 注入到 EMLOG_CONFIG.leaderboardApi）
    // 接口通过 ?ddz_action=xxx 参数区分不同操作
    // 后端在 wx_ddz.php 主文件中拦截处理（init.php 加载时执行，早于 Dispatcher）

    /**
     * 保存玩家分数到服务端
     * 接口: ?ddz_action=save_score (POST)
     */
    async saveScore(score, resultType = 'win') {
        if (!currentUser) {
            console.log('[Leaderboard] 未登录，不保存到服务器');
            return false;
        }

        try {
            console.log('[Leaderboard] 正在保存分数:', score, '结果:', resultType);

            const formData = new FormData();
            formData.append('score', score);
            formData.append('result', resultType);

            const response = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=save_score', {
                method: 'POST',
                credentials: 'include',
                body: formData
            });

            // 校验响应类型，防止 HTML 被当作 JSON 解析
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error('[Leaderboard] 保存分数返回非JSON:', text.substring(0, 200));
                return false;
            }

            const data = await response.json();
            console.log('[Leaderboard] 保存响应:', data);

            if (data.code === 0) {
                console.log('[Leaderboard] 分数保存成功');
                return true;
            } else {
                console.warn('[Leaderboard] 保存失败:', data.msg);
                return false;
            }
        } catch (error) {
            console.error('[Leaderboard] 保存分数失败:', error);
            return false;
        }
    },

    /**
     * 获取排行榜数据
     * 接口: ?ddz_action=get_ranking (GET)
     */
    async getRanking(limit = 20) {
        try {
            const response = await fetch(
                EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_ranking&limit=' + limit,
                { credentials: 'include' }
            );

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error('[Leaderboard] 获取排行榜返回非JSON:', text.substring(0, 200));
                return [];
            }

            const data = await response.json();

            if (data.code === 0 && data.data && data.data.entries) {
                return data.data.entries;
            }

            return [];
        } catch (error) {
            console.error('[Leaderboard] 获取排行榜失败:', error);
            return [];
        }
    },

    /**
     * 获取当前用户排名和积分
     * 接口: ?ddz_action=get_my_rank (GET)
     */
    async getMyRank() {
        if (!currentUser) return null;

        try {
            const response = await fetch(
                EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_my_rank',
                { credentials: 'include' }
            );

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error('[Leaderboard] 获取排名返回非JSON:', text.substring(0, 200));
                return null;
            }

            const data = await response.json();

            if (data.code === 0) {
                return data.data;
            }
            return null;
        } catch (error) {
            console.error('[Leaderboard] 获取排名失败:', error);
            return null;
        }
    },

    /**
     * 显示排行榜弹窗
     */
    async show() {
        const container = document.getElementById('leaderboardList');
        container.innerHTML = '<div style="text-align: center; color: #aaa; padding: 20px;">加载中...</div>';
        document.getElementById('leaderboardModal').classList.remove('hidden');

        const limit = window.WX_DDZ_MAX_ENTRIES || 50;
        const entries = await this.getRanking(limit);

        if (!entries || entries.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #aaa; padding: 20px;">暂无记录<br><small style="color:#666;">登录后游戏可记录积分</small></div>';
            return;
        }

        container.innerHTML = entries.map((item, index) => {
            // 解析生效效果
            let nameStyle = '';
            let nameSuffix = '';
            if (item.active_effects && item.active_effects.length > 0) {
                item.active_effects.forEach(function(effect) {
                    try {
                        var effectData = typeof effect.data === 'string' ? JSON.parse(effect.data) : effect.data;
                        if (effect.type === 'title_colored' && effectData.color) {
                            nameStyle += 'color:' + effectData.color + ';';
                        }
                        if (effect.type === 'title_effect' && effectData.effect === 'glow') {
                            var gc = effectData.color || 'gold';
                            nameStyle += 'text-shadow:0 0 8px ' + gc + ',0 0 16px ' + gc + ';';
                        }
                        if (effect.type === 'title_badge' && effectData.badge) {
                            nameSuffix = ' <span style="font-size:10px;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;padding:1px 6px;border-radius:8px;white-space:nowrap;">' + effectData.badge + '</span>';
                        }
                    } catch(e) {}
                });
            }
            var nameHtml = (item.is_ai ? '🤖 ' : '') + '<span style="' + nameStyle + '">' + (item.nickname || '匿名') + '</span>' + nameSuffix;
            // 头像HTML（放最前面）
            var avatarHtml = item.avatar
                ? '<img src="' + item.avatar + '" class="lb-avatar" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;" onerror="this.style.display=\'none\'" />'
                : (item.is_ai ? '' : '<span style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#e17055,#d63031);display:inline-flex;align-items:center;justify-content:center;font-size:12px;color:#fff;flex-shrink:0;">' + (item.nickname ? item.nickname.charAt(0) : '?') + '</span>');
            return '<div class="leaderboard-item ' + (item.is_ai ? 'ai-item' : '') + '" style="display:flex;align-items:center;gap:8px;padding:8px 12px;">' +
                '<span class="rank" style="width:24px;text-align:center;font-weight:bold;">#' + (item.rank || index + 1) + '</span>' +
                avatarHtml +
                '<span class="player-name-lb" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + nameHtml + '</span>' +
                '<span class="lb-stats" style="font-size:11px;color:#aaa;white-space:nowrap;margin:0 8px;">' +
                    '<span style="color:#2ecc71">' + (item.wins || 0) + '胜</span> ' +
                    '<span style="color:#e74c3c">' + (item.losses || 0) + '负</span>' +
                '</span>' +
                '<span class="player-score" style="font-weight:bold;color:#ffd700;">' + (item.score || 0) + '</span>' +
            '</div>';
        }).join('');
    },

    /**
     * 保存AI玩家分数到服务端
     * 接口: ?ddz_action=save_ai_score (POST)
     */
    async saveAIScore(member, score, resultType = 'win') {
        try {
            const formData = new FormData();
            formData.append('score', score);
            formData.append('result', resultType);
            formData.append('nickname', member.name);
            formData.append('avatar', member.avatar);

            const response = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=save_ai_score', {
                method: 'POST',
                credentials: 'include',
                body: formData
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                console.error('[Leaderboard] AI分数保存返回非JSON');
                return;
            }

            const data = await response.json();
            if (data.code === 0) {
                console.log('[Leaderboard] AI', member.name, '分数已保存');
            }
        } catch (error) {
            console.error('[Leaderboard] AI分数保存失败:', error);
        }
    }
};
