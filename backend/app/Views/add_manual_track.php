<?php
// app/views/add_manual_track.php
/** @var array $playlists  Tableau issu de Playlist::getAllSpotify(),
//               chaque élément contient ['spotify_id'=>'…','nom'=>'…',…]  */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Ajouter une musique manuellement</title>
  <style>
    .artist-field { margin-bottom: 0.5em; }
    .playlist-list { margin-top: 1em; }
  </style>
</head>
<body>
  <h1>Ajouter une musique manuelle</h1>

  <form method="POST" action="?page=addManual">
    <!-- Titre -->
    <div>
      <label for="titre">Titre :</label><br>
      <input type="text" id="titre" name="titre" required style="width: 300px;">
    </div>

    <!-- Artistes dynamiques -->
    <div id="artists-container">
      <label>Artiste(s) :</label><br>
      <div class="artist-field">
        <input type="text" name="artistes[]" placeholder="Nom de l’artiste" required style="width: 300px;">
      </div>
    </div>
    <button type="button" id="add-artist">+ Ajouter un artiste</button>

    <!-- Playlists (checkboxes) -->
    <fieldset class="playlist-list">
      <legend>Ajouter à la/les playlist(s) :</legend>
      <?php foreach ($playlists as $pl): ?>
        <div>
          <label>
            <input type="checkbox" name="playlists[]"
                   value="<?= htmlspecialchars($pl['spotify_id'], ENT_QUOTES) ?>">
            <?= htmlspecialchars($pl['nom'], ENT_QUOTES) ?>
          </label>
        </div>
      <?php endforeach; ?>
    </fieldset>

    <div style="margin-top:1em">
      <button type="submit">Valider</button>
    </div>
  </form>

  <script>
    // JavaScript minimal pour cloner un champ artiste
    document.getElementById('add-artist').addEventListener('click', function(){
      const container = document.getElementById('artists-container');
      const div = document.createElement('div');
      div.className = 'artist-field';
      div.innerHTML = '<input type="text" name="artistes[]" placeholder="Nom de l’artiste" required style="width:300px;">';
      container.appendChild(div);
    });
  </script>
</body>
</html>
