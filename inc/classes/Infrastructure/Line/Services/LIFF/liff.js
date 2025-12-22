(function($){
setTimeout(function(){
    const liffId = window.power_funnel_liff_js_data.liff_id; // 替換成你的 LIFF ID
    const apiUrl = window.power_funnel_liff_js_data.api_url; // 替換成你的 LIFF ID



    // 初始化 LIFF
    async function initLiff() {
        try {
            await liff.init({ liffId });
            console.log("LIFF 初始化成功");
        } catch (err) {
            console.error("LIFF 初始化失敗:", err);
        }
    }

    // 取得使用者資訊
    async function getUserProfile() {
        try {
            if (!liff.isLoggedIn()) {
                liff.login();
                return null;
            }
            const profile = await liff.getProfile();

            return {
                userId: profile.userId,
                name: profile.displayName,
                picture: profile.pictureUrl,
                os: liff.getOS(),
                version: liff.getVersion(),
                lineVersion: liff.getLineVersion(),
                isInClient: liff.isInClient(),
                isLoggedIn: liff.isLoggedIn(),
            };
        } catch (err) {
            console.error("取得使用者資訊失敗:", err);
            return null;
        }
    }

    // 發送 API 到後端
    async function sendUserToBackend(user) {
        if (!user) return;
        try {
            const urlParams = Object.fromEntries(new URLSearchParams(window.location.search));
            const res = await $.ajax({
                url: apiUrl,   // 你的後端 API endpoint
                method: "POST",
                contentType: "application/json",
                data: JSON.stringify({
                    urlParams,
                    ...user
                })
            });
            console.log("後端回應:", res);
        } catch (err) {
            console.error("API 發送失敗:", err);
        }
    }

    // 主流程
    async function main() {
        await initLiff();
        const user = await getUserProfile();
        await sendUserToBackend(user);
    }

    $(document).ready(() => {
        main();
    });

}, 3000)


})(jQuery)