(function () {
    'use strict';

    var config = window.XiaoGuHopeAdmin || {};
    var teams = Array.isArray(config.teams) ? config.teams : [];
    var source = document.querySelector('textarea[name="hopeGames"]');
    if (!source) return;

    source.classList.add('xiaogu-hope-source');
    var games = parseGames(source.value);
    var panel = document.createElement('section');
    panel.className = 'xiaogu-hope-admin';
    panel.innerHTML = [
        '<div class="xiaogu-hope-admin__heading">',
        '  <div><h3>可视化赛程管理</h3><p>选择联赛和主客队，再设置开赛时间。</p></div>',
        '  <strong class="xiaogu-hope-admin__count"></strong>',
        '</div>',
        '<div class="xiaogu-hope-admin__form">',
        '  <div class="xiaogu-hope-field"><label>联赛</label><select data-hope-league></select></div>',
        '  <div class="xiaogu-hope-field"><label>客队</label><div class="xiaogu-hope-team-select"><img data-away-logo alt=""><select data-away-team></select></div></div>',
        '  <button type="button" class="btn xiaogu-hope-swap" title="交换主客队" data-hope-swap>⇄</button>',
        '  <div class="xiaogu-hope-field"><label>主队</label><div class="xiaogu-hope-team-select"><img data-home-logo alt=""><select data-home-team></select></div></div>',
        '  <div class="xiaogu-hope-field"><label>比赛日期</label><input type="date" data-hope-date></div>',
        '  <div class="xiaogu-hope-field"><label>开赛时间</label><input type="time" data-hope-time step="60"></div>',
        '</div>',
        '<div class="xiaogu-hope-admin__actions"><span class="xiaogu-hope-admin__message" role="status"></span><button type="button" class="btn primary" data-hope-add>添加赛程</button></div>',
        '<div class="xiaogu-hope-admin__list"></div>'
    ].join('');
    source.insertAdjacentElement('afterend', panel);

    var league = panel.querySelector('[data-hope-league]');
    var away = panel.querySelector('[data-away-team]');
    var home = panel.querySelector('[data-home-team]');
    var awayLogo = panel.querySelector('[data-away-logo]');
    var homeLogo = panel.querySelector('[data-home-logo]');
    var date = panel.querySelector('[data-hope-date]');
    var time = panel.querySelector('[data-hope-time]');
    var message = panel.querySelector('.xiaogu-hope-admin__message');
    var list = panel.querySelector('.xiaogu-hope-admin__list');
    var count = panel.querySelector('.xiaogu-hope-admin__count');

    ['NBA', 'CBA'].forEach(function (name) {
        var option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        league.appendChild(option);
    });
    date.value = formatLocalDate(new Date());
    time.value = '19:35';

    function parseGames(raw) {
        return raw.split(/\r?\n/).map(function (line) {
            var parts = line.split('|').map(function (part) { return part.trim(); });
            if (parts.length < 6 || !parts[1] || !parts[3] || !parts[5]) return null;
            return {league: parts[0] || 'NBA', away: parts[1], awayLogo: parts[2],
                home: parts[3], homeLogo: parts[4], datetime: parts[5]};
        }).filter(Boolean);
    }

    function formatLocalDate(value) {
        var year = value.getFullYear();
        var month = String(value.getMonth() + 1).padStart(2, '0');
        var day = String(value.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function leagueTeams() {
        return teams.filter(function (team) { return team.league === league.value; });
    }

    function findTeam(id) {
        return teams.find(function (team) { return String(team.id) === String(id); });
    }

    function teamByName(name, leagueName) {
        return teams.find(function (team) { return team.name === name && team.league === leagueName; });
    }

    function fillTeams() {
        var currentAway = away.value;
        var currentHome = home.value;
        away.replaceChildren();
        home.replaceChildren();
        leagueTeams().forEach(function (team) {
            [away, home].forEach(function (select) {
                var option = document.createElement('option');
                option.value = team.id;
                option.textContent = team.name;
                select.appendChild(option);
            });
        });
        if (Array.from(away.options).some(function (item) { return item.value === currentAway; })) away.value = currentAway;
        if (Array.from(home.options).some(function (item) { return item.value === currentHome; })) home.value = currentHome;
        if (home.options.length > 1 && home.value === away.value) home.selectedIndex = 1;
        updateLogos();
    }

    function updateLogos() {
        var awayTeam = findTeam(away.value);
        var homeTeam = findTeam(home.value);
        awayLogo.src = awayTeam ? awayTeam.logo_url : '';
        homeLogo.src = homeTeam ? homeTeam.logo_url : '';
    }

    function syncSource() {
        games.sort(function (a, b) { return a.datetime.localeCompare(b.datetime); });
        source.value = games.map(function (game) {
            return [game.league, game.away, game.awayLogo, game.home, game.homeLogo, game.datetime].join('|');
        }).join('\n');
        source.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function makeTeam(team, label) {
        var wrap = document.createElement('div');
        wrap.className = 'xiaogu-hope-team';
        var image = document.createElement('img');
        image.src = team.logo;
        image.alt = '';
        var text = document.createElement('span');
        text.textContent = team.name + ' (' + label + ')';
        wrap.append(image, text);
        return wrap;
    }

    function render() {
        list.replaceChildren();
        count.textContent = games.length + ' 场比赛';
        if (!games.length) {
            var empty = document.createElement('p');
            empty.className = 'xiaogu-hope-admin__empty';
            empty.textContent = '还没有比赛，请在上方添加。';
            list.appendChild(empty);
            return;
        }
        games.forEach(function (game, index) {
            var card = document.createElement('article');
            card.className = 'xiaogu-hope-game';
            var teamWrap = document.createElement('div');
            teamWrap.className = 'xiaogu-hope-game__teams';
            var versus = document.createElement('span');
            versus.className = 'xiaogu-hope-game__versus';
            versus.textContent = 'VS';
            teamWrap.append(makeTeam({name: game.away, logo: game.awayLogo}, '客'), versus,
                makeTeam({name: game.home, logo: game.homeLogo}, '主'));
            var meta = document.createElement('div');
            meta.className = 'xiaogu-hope-game__meta';
            var metaLeague = document.createElement('strong');
            metaLeague.textContent = game.league;
            var metaTime = document.createElement('small');
            metaTime.textContent = game.datetime;
            meta.append(metaLeague, metaTime);
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-s';
            remove.textContent = '删除';
            remove.addEventListener('click', function () {
                games.splice(index, 1);
                syncSource();
                render();
            });
            card.append(teamWrap, meta, remove);
            list.appendChild(card);
        });
    }

    league.addEventListener('change', fillTeams);
    away.addEventListener('change', updateLogos);
    home.addEventListener('change', updateLogos);
    panel.querySelector('[data-hope-swap]').addEventListener('click', function () {
        var previous = away.value;
        away.value = home.value;
        home.value = previous;
        updateLogos();
    });
    panel.querySelector('[data-hope-add]').addEventListener('click', function () {
        var awayTeam = findTeam(away.value);
        var homeTeam = findTeam(home.value);
        message.textContent = '';
        if (!awayTeam || !homeTeam) {
            message.textContent = '请选择主客队。';
            return;
        }
        if (awayTeam.id === homeTeam.id) {
            message.textContent = '主队和客队不能相同。';
            return;
        }
        if (!date.value || !time.value) {
            message.textContent = '请选择比赛日期和时间。';
            return;
        }
        games.push({league: league.value, away: awayTeam.name, awayLogo: awayTeam.logo_url,
            home: homeTeam.name, homeLogo: homeTeam.logo_url, datetime: date.value + ' ' + time.value});
        syncSource();
        render();
        message.textContent = '已添加，请点击页面底部“保存设置”。';
        message.style.color = '#2e7d32';
    });

    fillTeams();
    games.forEach(function (game) {
        var awayTeam = teamByName(game.away, game.league);
        var homeTeam = teamByName(game.home, game.league);
        if (awayTeam) game.awayLogo = awayTeam.logo_url;
        if (homeTeam) game.homeLogo = homeTeam.logo_url;
    });
    syncSource();
    render();
}());
