jQuery(document).ready(function($) {
    const customHTML = `
        <div>
            <h1 class="folder-title">Folders</h1>
            <div class="folder-container">
                <div class="folder">
                    <img class="folder-icon" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">
                    <div class="folder-name">Results 2023</div>
                    <div class="folder-info">23 Files · 137 MB</div>
                </div>
                <div class="folder">
                    <img class="folder-icon" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">
                    <div class="folder-name">Market Analysis</div>
                    <div class="folder-info">8 Files · 56 MB</div>
                </div>
                <div class="folder">
                    <img class="folder-icon" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">
                    <div class="folder-name">All contract</div>
                    <div class="folder-info">37 Files · 92 MB</div>
                </div>
                <div class="folder">
                    <img class="folder-icon" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">
                    <div class="folder-name">Archieved</div>
                    <div class="folder-info">99 Files · 267 MB</div>
                </div>
            </div>
        </div>
    `;
    
    // Insertar el HTML personalizado antes del elemento con la clase 'tablenav top'
    $('.tablenav.top').before(customHTML);
});