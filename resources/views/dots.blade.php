<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dots & Boxes 2×2 — Human vs AI (Pure Minimax)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="m-0 min-h-screen grid place-items-center bg-slate-950 text-slate-200 p-6 font-sans">
    <div class="w-full max-w-[720px] bg-slate-900/80 border border-slate-800 rounded-2xl p-4 pt-4 shadow-2xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl m-0 mb-1">Dots & Boxes — 2×2</h1>
                <div class="text-sm text-slate-400">Human vs AI (pure Minimax, full tree search). Human always starts first.
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <span
                    class="inline-flex items-center gap-1.5 border border-slate-800 rounded-full px-2.5 py-1 text-xs bg-slate-950/70">
                    <svg width="10" height="10">
                        <circle cx="5" cy="5" r="5" fill="#22c55e" />
                    </svg>Player (Green)
                </span>
                <span
                    class="inline-flex items-center gap-1.5 border border-slate-800 rounded-full px-2.5 py-1 text-xs bg-slate-950/70">
                    <svg width="10" height="10">
                        <circle cx="5" cy="5" r="5" fill="#ef4444" />
                    </svg>AI (Red)
                </span>
            </div>
        </div>

        <div class="mt-2 flex gap-3 items-center">
            <button id="resetBtn"
                class="border border-slate-800 bg-slate-950/70 text-slate-200 px-3 py-2 rounded-lg cursor-pointer transition hover:bg-slate-900 hover:border-slate-700">Reset</button>
        </div>

        <div class="h-px bg-slate-800/70 my-2" aria-hidden="true"></div>

        <div class="grid place-items-center">
            <svg id="board" viewBox="0 0 300 300" width="100%" class="max-w-[520px]"></svg>
        </div>

        <div class="mt-2 pt-2 border-t border-dashed border-slate-800 flex items-center justify-between">
            <div id="statusNote" class="text-xs text-slate-400">Your turn.</div>
        </div>
    </div>

    <script>
        (function() {
            // ===== Geometry =====
            const margin = 30,
                step = 120,
                dotR = 10;
            const coords = [];
            for (let y = 0; y < 3; y++)
                for (let x = 0; x < 3; x++) coords.push({
                    x: margin + x * step,
                    y: margin + y * step
                });

            const horiz = [],
                vert = [];
            for (let r = 0; r < 3; r++)
                for (let c = 0; c < 2; c++) {
                    const a = r * 3 + c,
                        b = a + 1;
                    horiz.push([a, b]);
                }
            for (let c = 0; c < 3; c++)
                for (let r = 0; r < 2; r++) {
                    const a = r * 3 + c,
                        b = a + 3;
                    vert.push([a, b]);
                }

            const edges = [];
            horiz.forEach((h, i) => edges.push([h[0], h[1], 'H', i]));
            vert.forEach((v, i) => edges.push([v[0], v[1], 'V', i + 6]));

            const boxEdges = [];
            for (let r = 0; r < 2; r++)
                for (let c = 0; c < 2; c++) {
                    const top = r * 2 + c,
                        bottom = (r + 1) * 2 + c,
                        left = 6 + c * 2 + r,
                        right = 6 + (c + 1) * 2 + r;
                    boxEdges.push([top, right, bottom, left]);
                }

            const HUMAN = 0,
                AI = 1;
            let edgesTaken, edgeOwner, boxOwner, scoreH, scoreA, player, over;

            const svg = document.getElementById('board');
            const note = document.getElementById('statusNote');
            const resetBtn = document.getElementById('resetBtn');

            function buildSVG() {
                svg.innerHTML = '';
                for (let r = 0; r < 2; r++)
                    for (let c = 0; c < 2; c++) {
                        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                        rect.setAttribute('x', margin + c * step);
                        rect.setAttribute('y', margin + r * step);
                        rect.setAttribute('width', step);
                        rect.setAttribute('height', step);
                        rect.setAttribute('rx', 8);
                        rect.dataset.boxIndex = (r * 2 + c);
                        svg.appendChild(rect);
                    }

                edges.forEach((e, idx) => {
                    const a = coords[e[0]],
                        b = coords[e[1]];
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', a.x);
                    line.setAttribute('y1', a.y);
                    line.setAttribute('x2', b.x);
                    line.setAttribute('y2', b.y);
                    line.setAttribute('stroke-width', '8');
                    line.dataset.edgeIndex = idx;
                    svg.appendChild(line);
                });

                coords.forEach(p => {
                    const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    dot.setAttribute('cx', p.x);
                    dot.setAttribute('cy', p.y);
                    dot.setAttribute('r', dotR);
                    dot.setAttribute('class', 'fill-slate-200');
                    svg.appendChild(dot);
                });
            }

            function init() {
                edgesTaken = Array(12).fill(false);
                edgeOwner = Array(12).fill(-1);
                boxOwner = Array(4).fill(-1);
                scoreH = 0;
                scoreA = 0;
                player = HUMAN;
                over = false;

                buildSVG();
                bind();
                render();
                setNote("Your turn.");
            }

            function bind() {
                svg.querySelectorAll('line').forEach(line => {
                    line.addEventListener('click', () => {
                        if (over || player !== HUMAN) return;
                        const i = +line.dataset.edgeIndex;
                        if (edgesTaken[i]) return;
                        play(i, HUMAN);
                    });
                });
                resetBtn.onclick = init;
            }

            function setNote(t) {
                note.textContent = t;
            }

            function styleEdge(lineEl, {
                taken,
                owner,
                hoverable
            }) {
                lineEl.setAttribute('class', '');
                if (!taken) {
                    lineEl.classList.add('stroke-slate-500');
                    if (hoverable) {
                        lineEl.classList.add('hover:stroke-yellow-500', 'cursor-pointer');
                    } else lineEl.classList.add('cursor-default');
                } else {
                    lineEl.classList.add('cursor-default');
                    if (owner === HUMAN) lineEl.classList.add('stroke-green-500');
                    else lineEl.classList.add('stroke-red-500');
                }
            }

            function styleBox(rectEl, owner) {
                rectEl.setAttribute('class', 'transition-opacity duration-200');
                if (owner === -1) rectEl.classList.add('opacity-0');
                else if (owner === HUMAN) rectEl.classList.add('fill-green-500', 'opacity-20');
                else rectEl.classList.add('fill-red-500', 'opacity-20');
            }

            function render() {
                const lines = svg.querySelectorAll('line');
                lines.forEach(line => {
                    const i = +line.dataset.edgeIndex;
                    const taken = edgesTaken[i];
                    const owner = edgeOwner[i];
                    styleEdge(line, {
                        taken,
                        owner,
                        hoverable: !taken && player === HUMAN
                    });
                });
                svg.querySelectorAll('rect').forEach(rect => {
                    const bi = +rect.dataset.boxIndex;
                    styleBox(rect, boxOwner[bi]);
                });
            }

            function allMoves() {
                const m = [];
                for (let i = 0; i < 12; i++)
                    if (!edgesTaken[i]) m.push(i);
                return m;
            }

            function isTerminal() {
                return (scoreH + scoreA) === 4;
            }

            function checkCompletedBy(i) {
                const completed = [];
                for (let b = 0; b < 4; b++) {
                    const ed = boxEdges[b];
                    if (ed.includes(i)) {
                        let cnt = 0;
                        for (const e of ed)
                            if (edgesTaken[e]) cnt++;
                        if (cnt === 4 && boxOwner[b] === -1) completed.push(b);
                    }
                }
                return completed;
            }

            function apply(i, who) {
                edgesTaken[i] = true;
                edgeOwner[i] = who;
                const completed = checkCompletedBy(i);
                if (completed.length) {
                    for (const b of completed) boxOwner[b] = who;
                    if (who === HUMAN) scoreH += completed.length;
                    else scoreA += completed.length;
                } else {
                    player = (who === HUMAN) ? AI : HUMAN;
                }
                if (isTerminal()) over = true;
                return {
                    completed,
                    prevNext: (who === HUMAN) ? AI : HUMAN
                };
            }

            function undo(i, who, ctx) {
                edgesTaken[i] = false;
                edgeOwner[i] = -1;
                for (const b of ctx.completed) boxOwner[b] = -1;
                if (who === HUMAN) scoreH -= ctx.completed.length;
                else scoreA -= ctx.completed.length;
                over = false;
                player = (ctx.completed.length ? who : ctx.prevNext);
            }

            // ===== Pure Minimax (no depth) =====
            function minimax(maximizingAI) {
                if (isTerminal()) {
                    const diff = scoreA - scoreH;
                    return {
                        score: diff > 0 ? 1000 : diff < 0 ? -1000 : 0,
                        move: null
                    };
                }

                const moves = allMoves();
                if (maximizingAI) {
                    let best = {
                        score: -Infinity,
                        move: null
                    };
                    for (const m of moves) {
                        const ctx = apply(m, AI);
                        const res = minimax(ctx.completed.length ? true : false);
                        undo(m, AI, ctx);
                        if (res.score > best.score) best = {
                            score: res.score,
                            move: m
                        };
                    }
                    return best;
                } else {
                    let best = {
                        score: Infinity,
                        move: null
                    };
                    for (const m of moves) {
                        const ctx = apply(m, HUMAN);
                        const res = minimax(ctx.completed.length ? false : true);
                        undo(m, HUMAN, ctx);
                        if (res.score < best.score) best = {
                            score: res.score,
                            move: m
                        };
                    }
                    return best;
                }
            }

            function play(i, who) {
                if (over || edgesTaken[i]) return;
                const ctx = apply(i, who);
                render();
                if (over) {
                    setNote(endText());
                    return;
                }

                if (ctx.completed.length) {
                    setNote(who === HUMAN ? "Nice! You scored — go again." : "AI scored and goes again…");
                    if (who === AI) aiMove();
                } else {
                    if (who === HUMAN) {
                        setNote("AI is thinking…");
                        setTimeout(aiMove, 50);
                    } else setNote("Your turn.");
                }
            }

            function aiMove() {
                if (over) return;
                const {
                    move
                } = minimax(true);
                const choice = (move !== null) ? move : allMoves()[0];
                play(choice, AI);
            }

            function endText() {
                if (scoreH > scoreA) return "Game over — You win! 🎉";
                if (scoreA > scoreH) return "Game over — AI wins. 🤖";
                return "Game over — Draw.";
            }

            init();
        })();
    </script>
</body>

</html>
