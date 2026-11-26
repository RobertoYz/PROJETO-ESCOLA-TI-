package br.gov.escola.anjonexus;

import android.app.DownloadManager;
import android.content.Context;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;

import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

@CapacitorPlugin(name = "DownloadsKanban")
public class DownloadsKanban extends Plugin {
    @PluginMethod
    public void baixar(PluginCall chamada) {
        String endereco = chamada.getString("url", "");
        String token = chamada.getString("token", "");
        Uri uri = Uri.parse(endereco);
        if (!"https".equals(uri.getScheme()) || uri.getHost() == null || uri.getPath() == null
                || !uri.getPath().matches(".*/kanban/anexos/[0-9]+") || token.isEmpty()) {
            chamada.reject("O download requer uma sessão ativa e uma conexão HTTPS.");
            return;
        }
        String nome = chamada.getString("nome", "Anexo").replaceAll("[\\\\/:*?\"<>|\\p{Cntrl}]", "_");
        if (nome.trim().isEmpty() || nome.equals(".") || nome.equals("..")) nome = "Anexo";
        String destino = System.currentTimeMillis() + "-" + nome;
        try {
            DownloadManager.Request pedido = new DownloadManager.Request(uri)
                    .addRequestHeader("Authorization", "Bearer " + token)
                    .setTitle(nome)
                    .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                pedido.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, destino);
            } else {
                pedido.setDestinationInExternalFilesDir(getContext(), Environment.DIRECTORY_DOWNLOADS, destino);
            }
            DownloadManager downloads = (DownloadManager) getContext().getSystemService(Context.DOWNLOAD_SERVICE);
            downloads.enqueue(pedido);
            chamada.resolve();
        } catch (RuntimeException erro) {
            chamada.reject("Não foi possível iniciar o download do anexo.", erro);
        }
    }
}
