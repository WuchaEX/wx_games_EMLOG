/**
 * wx_mojang - 排行榜/积分提交
 */

const Leaderboard = {
    /**
     * 保存玩家分数
     */
    saveScore(scoreChange, resultData) {
        return fetch(`?plugin=wx_games&game=mj&mj_action=save_score`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                score_change: scoreChange,
                result: resultData.result || '',
                win_type: resultData.winType || '',
                fan_count: resultData.fanCount || 0,
                fan_type: resultData.fanType || '',
                hand_tiles: resultData.handTiles || '',
                final_hand: resultData.finalHand || '',
                win_tile: resultData.winTile || '',
                winner: resultData.winner || 'player',
                game_token: resultData.gameToken || ''
            })
        }).then(r => r.json());
    },

    /**
     * 获取排行榜
     */
    getRanking(limit) {
        return fetch(`?plugin=wx_games&game=mj&mj_action=get_ranking&limit=${limit || 50}`)
            .then(r => r.json())
            .then(d => d.data || {});
    },

    /**
     * 获取当前用户排名
     */
    getMyRank() {
        return fetch(`?plugin=wx_games&game=mj&mj_action=get_my_rank`)
            .then(r => r.json())
            .then(d => d.data || {});
    },

    /**
     * 开始新游戏
     */
    startGame() {
        return fetch(`?plugin=wx_games&game=mj&mj_action=start_game`)
            .then(r => r.json())
            .then(d => d.data || {});
    },

    /**
     * 完成游戏
     */
    completeGame(scoreChange, resultData) {
        return fetch(`?plugin=wx_games&game=mj&mj_action=complete_game`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                score_change: scoreChange,
                result: resultData.result || '',
                win_type: resultData.winType || '',
                fan_count: resultData.fanCount || 0,
                fan_type: resultData.fanType || '',
                hand_tiles: resultData.handTiles || '',
                final_hand: resultData.finalHand || '',
                win_tile: resultData.winTile || '',
                game_token: resultData.gameToken || '',
                winner: resultData.winner || 'player'
            })
        }).then(r => r.json());
    },

    /**
     * 检查未完成游戏
     */
    checkPending() {
        return fetch(`?plugin=wx_games&game=mj&mj_action=check_pending`)
            .then(r => r.json())
            .then(d => d.data || {});
    },

    /**
     * 获取用户流水
     */
    getUserLogs(limit, offset, uid) {
        const userUid = uid || 0;
        return fetch(`?plugin=wx_games&game=mj&mj_action=get_user_logs&uid=${userUid}&limit=${limit || 20}&offset=${offset || 0}`)
            .then(r => r.json())
            .then(d => {
                if (d.code === 0 && Array.isArray(d.data)) {
                    return { logs: d.data, total: d.data.length };
                }
                return { logs: [], total: 0 };
            });
    },

    /**
     * 获取Emlog站点积分
     */
    getEmlogCredits() {
        return fetch(`?plugin=wx_games&game=mj&mj_action=get_my_emlog_credits`)
            .then(r => r.json())
            .then(d => d.data || { credits: 0 });
    },

    /**
     * 显示排行榜弹窗（ddz 风格）
     */
    show() {
        // 游客模式检查
        const isGuest = (!currentUser || currentUser.uid <= 0);
        if (isGuest && MJ_GUEST_PLAY !== true) {
            showToast('请先登录');
            return;
        }
        const container = document.getElementById('leaderboardList') || this._ensureModal();
        container.innerHTML = '<div style="text-align:center;color:#aaa;padding:20px;">加载中...</div>';
        document.getElementById('leaderboardModal').classList.remove('hidden');

        this.getRanking(50).then(data => {
            const ranking = data.entries || data.ranking || [];
            if (ranking.length === 0) {
                container.innerHTML = '<div style="text-align:center;color:#aaa;padding:20px;">暂无数据</div>';
                return;
            }
            container.innerHTML = ranking.map((r, i) => {
                const rankDisplay = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '#' + (r.rank || i + 1);
                const avatarHtml = r.avatar
                    ? `<img src="${r.avatar}" class="lb-avatar" alt="">`
                    : `<span style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;color:#fff;flex-shrink:0;">${(r.nickname || '?')[0]}</span>`;
                return '<div class="leaderboard-item' + (r.is_ai ? ' ai-item' : '') + '">' +
                    '<span class="rank">' + rankDisplay + '</span>' +
                    avatarHtml +
                    '<span class="player-name">' + (r.uid && window.MJ_USER && r.uid === MJ_USER.uid && typeof renderPlayerName === 'function' ? renderPlayerName(r.nickname || '未知') : (r.nickname || '未知')) + '</span>' +
                    '<span class="lb-stats"><span style="color:#2ecc71">' + (r.wins || 0) + '胜</span> <span style="color:#e74c3c">' + (r.losses || 0) + '负</span></span>' +
                    '<span class="player-score">' + (r.score || 0) + '</span>' +
                '</div>';
            }).join('');
        });
    },

    /**
     * 确保排行榜弹窗存在（首次调用时动态创建）
     */
    _ensureModal() {
        let modal = document.getElementById('leaderboardModal');
        if (modal) return document.getElementById('leaderboardList');
        // 创建 ddz 风格弹窗
        const div = document.createElement('div');
        div.innerHTML = '<div class="mj-list-modal hidden" id="leaderboardModal">' +
            '<div class="mj-list-content" onclick="event.stopPropagation()">' +
                '<div class="list-title">🏆 排行榜</div>' +
                '<div class="list-body" id="leaderboardList"><div style="text-align:center;color:#aaa;padding:20px;">加载中...</div></div>' +
                '<div style="text-align:center;margin-top:20px;">' +
                    '<button class="btn btn-primary" onclick="document.getElementById(\'leaderboardModal\').classList.add(\'hidden\')">关闭</button>' +
                '</div>' +
            '</div>' +
        '</div>';
        document.body.appendChild(div.firstElementChild);
        return document.getElementById('leaderboardList');
    }
};
