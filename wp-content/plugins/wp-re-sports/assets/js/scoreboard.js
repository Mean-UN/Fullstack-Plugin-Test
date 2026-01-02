async function wrseFetchMatches(el) {
  const league = el.dataset.league || "all";
  const base =
    (window.WRSE_VARS && window.WRSE_VARS.endpoint) || "/wp-json/wrse/v1";
  const url = `${base.replace(/\/$/, "")}/matches?league=${encodeURIComponent(
    league
  )}`;

  try {
    const res = await fetch(url, {
      headers: { "X-WP-Nonce": (window.WRSE_VARS && WRSE_VARS.nonce) || "" },
    });
    const json = await res.json();
    return json.data || [];
  } catch (e) {
    console.error("WRSE scoreboard fetch failed", e);
    return [];
  }
}

function wrseRender(el, matches) {
  const skel = el.querySelector(".wrse-skel");
  const table = el.querySelector(".wrse-table");
  const tbody = table.querySelector("tbody");

  skel.style.display = "none";
  table.style.display = "";

  tbody.innerHTML = matches
    .map((m) => {
      const league = m.league || "";
      const time = m.start_time_utc || m.start_time || "";
      const home = m.home_team || m.home || "";
      const away = m.away_team || m.away || "";
      const score = m.score || `${m.score_home ?? 0}-${m.score_away ?? 0}`;
      const status = m.status || "";
      return `
      <tr>
        <td>${league}</td>
        <td>${time}</td>
        <td>${home}</td>
        <td>${score}</td>
        <td>${away}</td>
        <td>-</td>
        <td>-</td>
        <td>${status}</td>
      </tr>
    `;
    })
    .join("");
}

async function wrseInit() {
  document.querySelectorAll(".wrse-scoreboard").forEach(async (el) => {
    const load = async () => {
      const matches = await wrseFetchMatches(el);
      wrseRender(el, matches);
    };
    await load();
    setInterval(load, 30000); // 30s refresh (required)
  });
}
document.addEventListener("DOMContentLoaded", wrseInit);
