async function wrseFetchMatch(id) {
  const res = await fetch(`/sports/wp-json/wrse/v1/match/${id}`);
  return res.json();
}

async function wrseInitMC() {
  document.querySelectorAll(".wrse-match-center").forEach(async (el) => {
    const id = el.dataset.matchId;
    const header = el.querySelector(".wrse-mc-header");

    const load = async () => {
      const json = await wrseFetchMatch(id);
      const m = json.data;
      if (!m) {
        header.textContent = "Not found";
        return;
      }
      header.textContent = `${m.home_team} vs ${m.away_team} | ${m.score} | ${m.status}`;
      // Placeholder blocks (Step 7 will feed odds history)
      document.querySelector("#wrse-commentary").textContent =
        "Live commentary: (demo placeholder)";
      document.querySelector("#wrse-lineups").textContent =
        "Lineups: (demo placeholder)";
      document.querySelector("#wrse-stats").textContent =
        "Stats: (demo placeholder)";
      document.querySelector("#wrse-h2h").textContent =
        "H2H: (demo placeholder)";
    };

    await load();
    setInterval(load, 6000); // 5–7s required
  });
}
document.addEventListener("DOMContentLoaded", wrseInitMC);
