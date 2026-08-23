/**
 * TOURNIVOX Bracketing Manager - Interactive bracket viewer
 * Admin/organizer: click either fighting team or the match card to control the series.
 * Viewer: bracket remains read-only.
 */
class BracketViewer {
    constructor(containerId, tournamentId) {
        this.container = document.getElementById(containerId);
        this.tournamentId = tournamentId;
        this.scale = 1;
        this.panX = 0;
        this.panY = 0;
        this.isPanning = false;
        this.dragMatch = null;
        this.pollInterval = null;
        this.resizeHandler = () => this.drawConnectors();
        this.initControls();
        this.loadBracket();
        this.startPolling();
        window.addEventListener('resize', this.resizeHandler);
    }

    canManage() { return document.body.dataset.canManage === '1'; }

    initControls() {
        const controls = document.getElementById('bracketControls');
        if (!controls) return;
        controls.querySelector('[data-action="zoom-in"]')?.addEventListener('click', () => this.zoom(0.1));
        controls.querySelector('[data-action="zoom-out"]')?.addEventListener('click', () => this.zoom(-0.1));
        controls.querySelector('[data-action="reset"]')?.addEventListener('click', () => {
            this.scale = 1; this.panX = 0; this.panY = 0; this.applyTransform();
        });
        controls.querySelector('[data-action="fullscreen"]')?.addEventListener('click', () => this.toggleFullscreen());
        controls.querySelector('[data-action="print"]')?.addEventListener('click', () => window.print());
        controls.querySelector('[data-action="export-png"]')?.addEventListener('click', () => this.exportPng());
    }

    zoom(delta) {
        this.scale = Math.max(0.35, Math.min(2.25, this.scale + delta));
        this.applyTransform();
    }

    applyTransform() {
        const wrapper = this.container.querySelector('.bracket-wrapper');
        if (wrapper) wrapper.style.transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.scale})`;
    }

    toggleFullscreen() {
        const box = this.container.closest('.bracket-container');
        if (!box) return;
        const entering = !box.classList.contains('bracket-fullscreen');
        box.classList.toggle('bracket-fullscreen');
        let close = box.querySelector('.bracket-fullscreen-close');
        if (entering && !close) {
            close = document.createElement('button');
            close.type = 'button';
            close.className = 'bracket-fullscreen-close';
            close.setAttribute('aria-label', 'Exit full screen');
            close.innerHTML = '&times;';
            close.addEventListener('click', () => this.toggleFullscreen());
            box.appendChild(close);
        } else if (!entering && close) {
            close.remove();
        }
        setTimeout(() => this.drawConnectors(), 80);
    }

    async loadBracket() {
        try {
            const res = await fetch(`${APP_URL}/api/brackets.php?action=get&tournament_id=${this.tournamentId}`, {cache: 'no-store'});
            const data = await res.json();
            if (data.success) this.render(data.brackets);
        } catch (error) {
            console.error('Failed to load bracket', error);
        }
    }

    escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
    }

    render(brackets) {
        if (!brackets?.length) {
            this.container.innerHTML = '<div class="empty-state"><i class="bi bi-diagram-3"></i><p>No bracket generated yet</p></div>';
            return;
        }

        let html = '<div class="bracket-wrapper"><svg class="bracket-connectors" aria-hidden="true"></svg>';
        brackets.forEach(bracket => {
            html += '<section class="bracket-section">';
            if (bracket.type !== 'round_robin') {
                html += `<h6 class="text-center mb-3 bracket-section-title">${this.escapeHtml(this.bracketLabel(bracket.type))}</h6>`;
            }
            html += '<div class="bracket-rounds-row">';
            bracket.rounds.forEach(round => {
                html += `<div class="bracket-round"><div class="round-title" data-round-id="${round.id}" data-round-number="${round.number}" data-round-name="${this.escapeHtml(round.name)}" data-game-code="${this.escapeHtml(round.game_code || 'MLBB')}" data-format-type="${this.escapeHtml(round.format_type || 'best_of_series')}" data-best-of="${this.escapeHtml(round.best_of || 'BO3')}"><span>${this.escapeHtml(round.name)}</span>${this.canManage() ? '<button type="button" class="round-edit-btn" title="Edit round"><i class="bi bi-pencil-square"></i></button>' : ''}</div><div class="round-meta">${this.escapeHtml(round.game_code || '')} · ${this.escapeHtml((round.best_of || 'BO3'))}</div>`;
                // Future rounds remain hidden until at least one winner reaches them.
                round.matches.forEach(match => {
                    if (match.team1_id || match.team2_id || match.winner_id) html += this.renderMatch(match);
                });
                html += '</div>';
            });
            html += '</div></section>';
        });
        html += '</div>';
        this.container.innerHTML = html;

        this.bindRoundEditing();
        this.bindMatchEvents();
        this.bindPanning();
        this.applyTransform();
        requestAnimationFrame(() => this.drawConnectors());
    }

    renderMatch(match) {
        const statusClass = match.status === 'live' ? 'live' : match.status === 'finished' ? 'finished' : '';
        const team1Class = match.winner_id == match.team1_id ? 'winner' : (match.status === 'finished' && match.team1_id ? 'loser' : '');
        const team2Class = match.winner_id == match.team2_id ? 'winner' : (match.status === 'finished' && match.team2_id ? 'loser' : '');
        const clickable = this.canManage() && (match.team1_id || match.team2_id) ? ' is-manageable' : '';
        return `<div class="bracket-match ${statusClass}${clickable}" data-match-id="${match.id}" data-next-match-id="${match.next_match_id || ''}" ${this.canManage() ? 'draggable="true" tabindex="0" role="button" aria-label="Open series control"' : ''}>
            <div class="match-status-bar ${this.escapeHtml(match.status)}"></div>
            <div class="match-team ${team1Class} ${!match.team1_name ? 'empty' : ''}" data-team-slot="1">
                <span class="team-name">${this.escapeHtml(match.team1_name || 'TBD')}</span>
                <span class="team-score">${match.team1_score ?? ''}</span>
            </div>
            <div class="match-team ${team2Class} ${!match.team2_name ? 'empty' : ''}" data-team-slot="2">
                <span class="team-name">${this.escapeHtml(match.team2_name || 'TBD')}</span>
                <span class="team-score">${match.team2_score ?? ''}</span>
            </div>
        </div>`;
    }

    bracketLabel(type) {
        return {winners:'Winners Bracket', losers:'Losers Bracket', grand_finals:'Grand Final', round_robin:'Round Robin'}[type] || type;
    }

    async exportPng() {
        const target = this.container.querySelector('.bracket-wrapper');
        if (!target) return;
        const oldTransform = target.style.transform;
        target.style.transform = 'none';
        try {
            if (typeof html2canvas !== 'function') {
                alert('Image exporter could not load. Connect to the internet once, reload this page, and try again.');
                return;
            }
            const canvas = await html2canvas(target, {
                backgroundColor: '#110d0e',
                scale: 2,
                useCORS: true,
                logging: false,
                width: target.scrollWidth,
                height: target.scrollHeight,
                windowWidth: target.scrollWidth,
                windowHeight: target.scrollHeight
            });
            const link = document.createElement('a');
            link.download = `TOURNIVOX_Bracket_${this.tournamentId}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        } finally {
            target.style.transform = oldTransform;
        }
    }

    bindRoundEditing() {
        this.container.querySelectorAll('.round-edit-btn').forEach(btn => btn.addEventListener('click', event => {
            event.stopPropagation();
            const title = btn.closest('.round-title');
            this.openRoundEditor(title);
        }));
    }

    openRoundEditor(title) {
        document.getElementById('tournivoxRoundEditor')?.remove();
        const canDelete = Number(title.dataset.roundNumber) > 1;
        const dialog = document.createElement('dialog');
        dialog.id = 'tournivoxRoundEditor';
        dialog.className = 'round-editor-dialog';
        dialog.innerHTML = `<form method="dialog" class="round-editor-card">
            <div class="round-editor-head"><div><h4>Edit Round</h4><p>Change the title, game, format, and series before matches begin.</p></div><button value="cancel" class="round-dialog-close" aria-label="Close">&times;</button></div>
            <div class="round-editor-grid">
                <label>Round Title<input id="roundEditorName" class="form-control" maxlength="100" required value="${this.escapeHtml(title.dataset.roundName)}"></label>
                <label>Game<select id="roundEditorGame" class="form-select">
                    <option value="MLBB">Mobile Legends: Bang Bang</option><option value="CODM">Call of Duty: Mobile</option><option value="HOK">Honor of Kings</option><option value="VALORANT">VALORANT</option><option value="DOTA2">Dota 2</option><option value="LOL">League of Legends</option><option value="PUBGM">PUBG Mobile</option>
                </select></label>
                <label>Format<select id="roundEditorFormat" class="form-select">
                    <option value="best_of_series">Best-of Series</option><option value="single_elimination">Single Elimination</option><option value="double_elimination">Double Elimination</option><option value="round_robin">Round Robin</option><option value="swiss">Swiss</option><option value="group_stage">Group Stage</option><option value="hybrid">Hybrid</option><option value="gauntlet">Gauntlet</option><option value="custom">Custom</option>
                </select></label>
                <label>Best Of<select id="roundEditorBestOf" class="form-select"><option>BO1</option><option>BO2</option><option>BO3</option><option>BO5</option><option>BO7</option></select></label>
            </div>
            <div class="round-editor-note"><i class="bi bi-shield-lock"></i> Editing and removal are automatically locked after any match becomes live or finished.</div>
            <div class="round-editor-actions">${canDelete ? '<button type="button" class="btn btn-outline-danger" id="roundEditorDelete"><i class="bi bi-trash"></i> Remove Round</button>' : '<span></span>'}<div><button value="cancel" class="btn btn-outline-primary">Cancel</button><button type="button" class="btn btn-primary" id="roundEditorSave"><i class="bi bi-check2"></i> Save Changes</button></div></div>
        </form>`;
        document.body.appendChild(dialog);
        dialog.querySelector('#roundEditorGame').value = title.dataset.gameCode || 'MLBB';
        dialog.querySelector('#roundEditorFormat').value = title.dataset.formatType || 'best_of_series';
        dialog.querySelector('#roundEditorBestOf').value = title.dataset.bestOf || 'BO3';
        dialog.querySelector('#roundEditorSave').addEventListener('click', async () => {
            const payload = {
                action: 'update_round', round_id: title.dataset.roundId,
                round_name: dialog.querySelector('#roundEditorName').value.trim(),
                game_code: dialog.querySelector('#roundEditorGame').value,
                format_type: dialog.querySelector('#roundEditorFormat').value,
                best_of: dialog.querySelector('#roundEditorBestOf').value
            };
            if (!payload.round_name) return dialog.querySelector('#roundEditorName').focus();
            const result = await apiCall(`${APP_URL}/api/brackets.php`, payload);
            if (result.success) { dialog.close(); dialog.remove(); this.loadBracket(); }
        });
        dialog.querySelector('#roundEditorDelete')?.addEventListener('click', async () => {
            if (!confirm('Remove this round and all waiting matches inside it? Only the last round can be removed.')) return;
            const result = await apiCall(`${APP_URL}/api/brackets.php`, {action:'delete_round', round_id:title.dataset.roundId});
            if (result.success) { dialog.close(); dialog.remove(); this.loadBracket(); }
        });
        dialog.addEventListener('close', () => dialog.remove());
        dialog.showModal();
    }

    bindMatchEvents() {
        this.container.querySelectorAll('.bracket-match').forEach(el => {
            if (this.canManage()) {
                const open = event => {
                    if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
                    event.preventDefault(); event.stopPropagation();
                    this.openMatchModal(el.dataset.matchId);
                };
                // Clicking either team row or any part of the card opens the series controller.
                el.addEventListener('click', open);
                el.addEventListener('keydown', open);
                el.addEventListener('dragstart', event => { this.dragMatch = el; el.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', el.dataset.matchId); });
                el.addEventListener('dragend', () => { el.classList.remove('is-dragging'); this.container.querySelectorAll('.drag-over').forEach(x => x.classList.remove('drag-over')); this.dragMatch = null; });
                el.addEventListener('dragover', event => { event.preventDefault(); el.classList.add('drag-over'); });
                el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
                el.addEventListener('drop', event => this.handleDrop(event, el));
            }
        });
    }

    bindPanning() {
        this.container.onmousedown = event => {
            if (event.button !== 0 || event.target.closest('.bracket-match,.round-edit-btn,.bracket-controls')) return;
            this.isPanning = true;
            this.startX = event.clientX - this.panX;
            this.startY = event.clientY - this.panY;
            this.container.classList.add('is-panning');
        };
        this.container.onmousemove = event => {
            if (!this.isPanning) return;
            this.panX = event.clientX - this.startX;
            this.panY = event.clientY - this.startY;
            this.applyTransform();
        };
        this.container.onmouseup = this.container.onmouseleave = () => {
            this.isPanning = false;
            this.container.classList.remove('is-panning');
        };
        this.container.addEventListener('wheel', event => {
            if (!event.ctrlKey) return;
            event.preventDefault();
            this.zoom(event.deltaY < 0 ? 0.1 : -0.1);
        }, {passive:false});
    }

    drawConnectors() {
        const wrapper = this.container.querySelector('.bracket-wrapper');
        const svg = wrapper?.querySelector('.bracket-connectors');
        if (!wrapper || !svg) return;
        const width = Math.max(wrapper.scrollWidth, wrapper.getBoundingClientRect().width / Math.max(this.scale, .01));
        const height = Math.max(wrapper.scrollHeight, 500);
        svg.setAttribute('width', width); svg.setAttribute('height', height);
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.innerHTML = '';
        const wrapperRect = wrapper.getBoundingClientRect();
        wrapper.querySelectorAll('.bracket-match[data-next-match-id]').forEach(source => {
            const nextId = source.dataset.nextMatchId;
            if (!nextId) return;
            const target = wrapper.querySelector(`.bracket-match[data-match-id="${CSS.escape(nextId)}"]`);
            if (!target) return;
            const a = source.getBoundingClientRect(), b = target.getBoundingClientRect();
            const inv = 1 / Math.max(this.scale, .01);
            const x1 = (a.right - wrapperRect.left) * inv;
            const y1 = (a.top + a.height / 2 - wrapperRect.top) * inv;
            const x2 = (b.left - wrapperRect.left) * inv;
            const y2 = (b.top + b.height / 2 - wrapperRect.top) * inv;
            const mid = x1 + Math.max(24, (x2 - x1) / 2);
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', `M ${x1} ${y1} H ${mid} V ${y2} H ${x2}`);
            path.setAttribute('class', 'bracket-connector-path');
            svg.appendChild(path);
        });
    }

    async handleDrop(event, targetEl) {
        event.preventDefault(); event.stopPropagation(); targetEl.classList.remove('drag-over');
        if (!this.dragMatch || this.dragMatch === targetEl) return;
        const result = await apiCall(`${APP_URL}/api/brackets.php`, {action:'swap_matches', source_match_id:this.dragMatch.dataset.matchId, target_match_id:targetEl.dataset.matchId});
        this.dragMatch = null;
        if (result.success) this.loadBracket();
    }

    async openMatchModal(matchId) {
        try {
            const response = await fetch(`${APP_URL}/api/matches.php?action=get&id=${encodeURIComponent(matchId)}`, {cache:'no-store'});
            const data = await response.json();
            if (!data.success) { showToast(data.message || 'Unable to open match', 'danger'); return; }
            const m = data.match; window.currentMatch = m; window.currentMatchTeams = data.teams || [];
            const modal = document.getElementById('matchModal');
            if (!modal) return;
            document.getElementById('matchModalTitle').textContent = 'Series Control';
            document.getElementById('matchTeam1').textContent = m.team1_name || 'TBD';
            document.getElementById('matchTeam2').textContent = m.team2_name || 'TBD';
            const teamEditor = document.getElementById('matchTeamEditor');
            const team1Select = document.getElementById('matchTeam1Select');
            const team2Select = document.getElementById('matchTeam2Select');
            const canEditTeams = this.canManage() && !['live','finished'].includes(m.status);
            if (teamEditor) teamEditor.style.display = canEditTeams ? 'block' : 'none';
            if (team1Select && team2Select) {
                const optionHtml = (selectedId) => '<option value="">Select team</option>' + (data.teams || []).map(team => `<option value="${team.id}" ${Number(team.id) === Number(selectedId) ? 'selected' : ''}>${this.escapeHtml(team.name)}</option>`).join('');
                team1Select.innerHTML = optionHtml(m.team1_id);
                team2Select.innerHTML = optionHtml(m.team2_id);
            }
            document.getElementById('matchTeam1Score').value = Number(m.team1_score || 0);
            document.getElementById('matchTeam2Score').value = Number(m.team2_score || 0);
            document.getElementById('matchBestOf').value = m.best_of || 'BO3';
            document.getElementById('matchStatus').value = m.status || 'waiting';
            document.getElementById('matchNotes').value = m.notes || '';
            document.getElementById('matchId').value = m.id;
            document.getElementById('team1GameWin').textContent = `${m.team1_name || 'Team 1'} Won This Game`;
            document.getElementById('team2GameWin').textContent = `${m.team2_name || 'Team 2'} Won This Game`;
            document.getElementById('team1GameWin').disabled = !m.team1_id || m.status === 'finished';
            document.getElementById('team2GameWin').disabled = !m.team2_id || m.status === 'finished';
            refreshScore();
            const manageSection = document.getElementById('matchManageSection');
            if (manageSection) manageSection.style.display = this.canManage() ? 'block' : 'none';
            bootstrap.Modal.getOrCreateInstance(modal).show();
        } catch (error) {
            console.error(error); showToast('Unable to load match details.', 'danger');
        }
    }

    startPolling() { this.pollInterval = setInterval(() => this.loadBracket(), 10000); }
    destroy() { if (this.pollInterval) clearInterval(this.pollInterval); window.removeEventListener('resize', this.resizeHandler); }
}


async function saveMatchTeams() {
    if (document.body.dataset.canManage !== '1' || !window.currentMatch) return;
    const team1Id = Number(document.getElementById('matchTeam1Select')?.value || 0);
    const team2Id = Number(document.getElementById('matchTeam2Select')?.value || 0);
    if (!team1Id || !team2Id) return showToast('Select both teams.', 'warning');
    if (team1Id === team2Id) return showToast('A team cannot play against itself.', 'warning');
    const result = await apiCall(`${APP_URL}/api/matches.php`, {
        action: 'update_teams',
        match_id: window.currentMatch.id,
        team1_id: team1Id,
        team2_id: team2Id
    });
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('matchModal'))?.hide();
        window.bracketViewer?.loadBracket();
    }
}

function winsNeeded() {
    const bo = document.getElementById('matchBestOf').value;
    const games = parseInt(bo.replace('BO',''), 10);
    return Math.floor(games / 2) + 1;
}
function refreshScore() {
    const a = Number(document.getElementById('matchTeam1Score').value || 0);
    const b = Number(document.getElementById('matchTeam2Score').value || 0);
    document.getElementById('scoreDisplay').textContent = `${a} - ${b}`;
}
async function recordGameWin(side) {
    if (document.body.dataset.canManage !== '1' || !window.currentMatch) return;
    const a = document.getElementById('matchTeam1Score');
    const b = document.getElementById('matchTeam2Score');
    const bo = document.getElementById('matchBestOf').value;
    const maxGames = parseInt(bo.replace('BO',''), 10);

    if (Number(a.value) + Number(b.value) >= maxGames) {
        return showToast('This series already has the maximum number of games.', 'warning');
    }

    if (side === 1) a.value = Number(a.value || 0) + 1;
    else b.value = Number(b.value || 0) + 1;

    if (document.getElementById('matchStatus').value === 'waiting') {
        document.getElementById('matchStatus').value = 'live';
    }

    refreshScore();

    // TOURNIVOX Round Robin BO2 rule: 2-0 = win, 1-1 = draw.
    if (bo === 'BO2' && Number(a.value) + Number(b.value) >= 2) {
        let result;
        if (Number(a.value) === Number(b.value)) {
            result = await apiCall(`${APP_URL}/api/matches.php`, {
                action: 'declare_draw',
                match_id: document.getElementById('matchId').value,
                team1_score: Number(a.value),
                team2_score: Number(b.value)
            });
        } else {
            const winner = Number(a.value) > Number(b.value) ? window.currentMatch.team1_id : window.currentMatch.team2_id;
            result = await apiCall(`${APP_URL}/api/matches.php`, {
                action: 'declare_winner',
                match_id: document.getElementById('matchId').value,
                winner_id: winner,
                team1_score: Number(a.value),
                team2_score: Number(b.value)
            });
        }

        if (result.success) {
            showToast(Number(a.value) === Number(b.value) ? 'BO2 draw recorded.' : 'Series finished. Result recorded!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('matchModal'))?.hide();
            window.bracketViewer?.loadBracket();
        }
        return;
    }

    const need = winsNeeded();
    if (Number(a.value) >= need || Number(b.value) >= need) {
        const winner = Number(a.value) >= need ? window.currentMatch.team1_id : window.currentMatch.team2_id;
        const result = await apiCall(`${APP_URL}/api/matches.php`, {
            action: 'declare_winner',
            match_id: document.getElementById('matchId').value,
            winner_id: winner,
            team1_score: Number(a.value),
            team2_score: Number(b.value)
        });
        if (result.success) {
            showToast('Series finished. Winner advanced automatically!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('matchModal'))?.hide();
            window.bracketViewer?.loadBracket();
        }
    } else {
        await updateMatch(false);
    }
}
function resetSeriesScore() {
    document.getElementById('matchTeam1Score').value = 0;
    document.getElementById('matchTeam2Score').value = 0;
    refreshScore(); updateMatch(false);
}
async function updateMatch(close = true) {
    const data = {action:'update', match_id:document.getElementById('matchId').value, best_of:document.getElementById('matchBestOf').value, status:document.getElementById('matchStatus').value, notes:document.getElementById('matchNotes').value, team1_score:Number(document.getElementById('matchTeam1Score').value), team2_score:Number(document.getElementById('matchTeam2Score').value)};
    const result = await apiCall(`${APP_URL}/api/matches.php`, data);
    if (result.success) {
        if (close) bootstrap.Modal.getInstance(document.getElementById('matchModal'))?.hide();
        window.bracketViewer?.loadBracket();
    }
}
async function generateBracket(tournamentId, seedingType='random') {
    if (!confirm('Generate the bracket from the actual approved teams? Existing bracket matches will be replaced.')) return;
    const result = await apiCall(`${APP_URL}/api/brackets.php`, {action:'generate', tournament_id:tournamentId, seeding_type:seedingType});
    if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 700); }
}
