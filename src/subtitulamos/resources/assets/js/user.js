/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import "../css/user.scss";
import { $getEle } from "./utils";

const $roleChangeForm = $getEle("#reset-user-pwd");
if ($roleChangeForm) {
  let manualSubmit = false;
  $roleChangeForm.addEventListener("submit", function (e) {
    if (manualSubmit) {
      return;
    }

    e.preventDefault(); // Don't submit the form

    Swal.fire({
      type: "warning",
      confirmButtonText: "Reiniciar",
      cancelButtonText: "Cancelar",
      showCancelButton: true,
      html:
        "Estás a punto de reiniciar la contraseña de este usuario. Se generará una nueva contraseña, y su contraseña actual dejará de valer para acceder." +
        "<br/><br/>¿Estás seguro de querer continuar?",
    }).then((result) => {
      if (result.value) {
        manualSubmit = true;
        $roleChangeForm.submit();
      }
    });
  });
}
