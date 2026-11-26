package br.gov.escola.anjonexus;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(android.os.Bundle savedInstanceState) {
        registerPlugin(DownloadsKanban.class);
        super.onCreate(savedInstanceState);
    }
}
