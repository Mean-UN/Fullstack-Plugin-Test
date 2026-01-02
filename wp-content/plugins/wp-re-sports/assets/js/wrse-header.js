document.addEventListener("click", (e) => {
  const dd = e.target.closest(".wrse-dd");
  document.querySelectorAll(".wrse-dd.is-open").forEach((x) => {
    if (x !== dd) x.classList.remove("is-open");
  });
  if (dd) dd.classList.toggle("is-open");
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document
      .querySelectorAll(".wrse-dd.is-open")
      .forEach((x) => x.classList.remove("is-open"));
  }
});
