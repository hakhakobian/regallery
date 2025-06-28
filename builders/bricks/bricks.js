function reacg_loadApp() {
  const reacgLoadApp = document.getElementById('reacg-loadApp');
  if ( reacgLoadApp ) {
    document.querySelectorAll('div.reacg-gallery[data-gallery-id]').forEach((div) => {
      const galleryId = div.getAttribute('data-gallery-id');
      if ( !galleryId ) return;

      reacgLoadApp.setAttribute('data-id', 'reacg-root' + galleryId);
      reacgLoadApp.click();
    });
  }
}