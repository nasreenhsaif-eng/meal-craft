{{-- Admin shell intro: sync gate before paint + shell fade when class is removed. Only included from layouts/app/sidebar. --}}
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&display=swap" rel="stylesheet">
<style>
    #mc-admin-shell {
        transition: opacity 0.75s ease-out;
    }

    html.mc-intro-pending #mc-admin-shell {
        opacity: 0;
        pointer-events: none;
    }
</style>
<script>
    (() => {
        try {
            let sid = sessionStorage.getItem('mealcraft_tab_sid');
            if (!sid) {
                sid = crypto.randomUUID();
                sessionStorage.setItem('mealcraft_tab_sid', sid);
            }
            const key = 'mealcraft_intro_done:' + sid;
            if (localStorage.getItem(key) !== '1') {
                document.documentElement.classList.add('mc-intro-pending');
            }
        } catch (_) {
            /* ignore private mode / quota */
        }
    })();
</script>
