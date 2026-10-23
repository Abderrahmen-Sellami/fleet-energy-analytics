// Si la session expire pendant qu'un écran est ouvert, le premier 401 d'un /api/* ramène à l'écran de connexion.
(function () {
  if (window.__sessionGardeInstallee) return;
  window.__sessionGardeInstallee = true;

  const fetchOriginal = window.fetch.bind(window);
  let redirection = false;

  window.fetch = async function (...args) {
    const reponse = await fetchOriginal(...args);
    if (reponse.status === 401 && !redirection) {
      const url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url) || '';
      if (url.startsWith('/api/') || url.startsWith(location.origin + '/api/')) {
        redirection = true;
        location.href = '/connexion?retour=' + encodeURIComponent(location.pathname + location.search);
      }
    }
    return reponse;
  };
})();
