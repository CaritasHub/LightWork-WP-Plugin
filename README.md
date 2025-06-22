# LightWork-WP-Plugin

**Rendi WordPress facile come WordPress, ma potente come se lo avessi creato tu.**

## Descrizione

Il **LightWork-WP-Plugin** per WordPress permette di creare, configurare e gestire facilmente **Custom Post Types (CTP)** personalizzati. Grazie all'integrazione con **Advanced Custom Fields (ACF)**, il plugin offre una soluzione potente sia per gli utenti avanzati che per quelli meno esperti. Consente di creare CTP avanzati, associarvi campi personalizzati, template dinamici e fornisce anche rotte REST per una gestione completamente automatizzata dei dati.

Il plugin è progettato per essere facilmente configurabile tramite un **wizard di amministrazione**, con una gestione automatica delle modifiche dei CTP, senza compromettere la performance del sito. Le rotte REST personalizzate permettono una completa personalizzazione delle query, rendendo il plugin ideale per sviluppatori avanzati e per l'uso su siti con un elevato volume di contenuti.

## Funzionalità

### 1. Creazione e gestione di CTP (Custom Post Types)
- Crea facilmente nuovi **Custom Post Types (CTP)** tramite un'interfaccia semplice e chiara.
- Gestione completa dei CTP con tutte le opzioni di configurazione, inclusa la visibilità e i permessi.
- I CTP vengono visualizzati e gestiti nel backend di WordPress in modo intuitivo.

### 2. Integrazione con ACF (Advanced Custom Fields)
- Collega **ACF** per aggiungere campi personalizzati (testo, immagini, selettori, etc.) ai tuoi CTP.
- Ogni campo personalizzato può essere facilmente modificato tramite l'interfaccia di ACF, senza bisogno di scrivere codice.

### 3. Template dinamici per CTP
- Ogni CTP può essere associato a un **template dinamico** che si popola automaticamente con i campi ACF.
- I template possono essere facilmente personalizzati tramite il sistema di temi di WordPress, con la possibilità di aggiungere codice PHP per adattarli alle necessità specifiche.

### 4. Rotte REST per il recupero dei dati
- Fornisce **rotte REST** per accedere ai CTP tramite API, con parametri di filtraggio avanzato.
- Supporta **paginazione**, **ordinamento** (per data, titolo, etc.), e **filtraggio per metadati**.
- Le rotte REST possono essere utilizzate per il recupero dei dati frontend, consentendo una gestione dinamica dei contenuti tramite JavaScript.

### 5. Interfaccia wizard per amministratori
- Una **wizard di configurazione** guida l'amministratore passo passo nel processo di creazione e gestione dei CTP.
- Gli amministratori possono scegliere facilmente i campi personalizzati da associare ai CTP e configurare i template tramite una semplice interfaccia.

### 6. Aggiornamenti massivi dei CTP
- Il plugin supporta l'aggiornamento di più istanze di un CTP in modalità **batch**.
- L'operazione di aggiornamento può essere eseguita in **background** utilizzando **WP Cron**, per evitare blocchi o rallentamenti nel sito.

## Installazione

### Requisiti
- WordPress 5.0 o superiore.
- Il plugin **Advanced Custom Fields (ACF)** deve essere installato e attivo.

### Passaggi per l'installazione
1. Carica la cartella del plugin nella directory `wp-content/plugins`.
2. Attiva il plugin tramite la dashboard di WordPress.
3. Una volta attivato, accedi al menu di amministrazione del plugin per iniziare la configurazione.
4. Usa il wizard di configurazione per creare il primo CTP, aggiungere campi ACF e configurare i template.

## Come funziona

### 1. Creazione e configurazione dei CTP tramite Wizard
- **Wizard di creazione CTP**: Gli amministratori possono creare facilmente nuovi **Custom Post Types (CTP)**. Il wizard guida l'amministratore attraverso il processo di configurazione del CTP, consentendo di definire nome, visibilità e altre opzioni.
- **Associazione di campi ACF**: Il plugin integra **ACF** per la gestione dei campi personalizzati. L'amministratore può aggiungere e configurare facilmente i campi tramite l'interfaccia di ACF.
- **Associazione di template**: Una volta creato un CTP, il plugin consente di collegarlo a un template dinamico, che verrà automaticamente popolato con i dati dei campi ACF.

### 2. Visualizzazione dei CTP
- Ogni CTP è associato a un template che si popola automaticamente con i dati. Gli utenti possono visualizzare e interagire con i CTP sul frontend tramite i template personalizzati.
- I template possono essere modificati tramite il tema di WordPress, per adattarli al design del sito.

### 3. Rotte REST per il recupero dei CTP
Le rotte REST personalizzate consentono di recuperare i CTP tramite API, con supporto per:
- **Filtraggio**: È possibile filtrare i risultati per metadati, categorie, tag, etc.
- **Ordinamento**: I risultati possono essere ordinati per data, titolo o qualsiasi campo personalizzato.
- **Paginazione**: Per evitare il caricamento di troppi risultati in una sola chiamata, il plugin supporta la paginazione.

**Esempio di rotta REST per recupero CTP**:
```php
function custom_plugin_rest_routes() {
    register_rest_route( 'custom_plugin/v1', '/items/', array(
        'methods' => 'GET',
        'callback' => 'get_custom_items',
        'args' => array(
            'category' => array(
                'validate_callback' => function( $param, $request, $key ) {
                    return is_string( $param );
                },
            ),
            'orderby' => array(
                'validate_callback' => function( $param, $request, $key ) {
                    return in_array( $param, ['date', 'title'], true );
                },
            ),
            'page' => array(
                'validate_callback' => function( $param, $request, $key ) {
                    return is_int( $param );
                },
            ),
        ),
    ));
}

add_action( 'rest_api_init', 'custom_plugin_rest_routes' );
````

### 4. Aggiornamenti massivi e gestione asincrona

Quando si aggiorna un CTP, è possibile aggiornare le istanze in modalità **batch**, per evitare blocchi del sito. Le modifiche vengono eseguite in **background** utilizzando **WP Cron**.

**Esempio di aggiornamento massivo**:

```php
function update_custom_items_in_batch() {
    $args = array(
        'post_type' => 'custom_item',
        'posts_per_page' => 100, // Processa 100 alla volta
        'paged' => 1,
    );

    $query = new WP_Query( $args );
    while ( $query->have_posts() ) {
        $query->the_post();
        // Aggiorna i campi ACF o altre proprietà
        update_field('custom_field', 'new_value', get_the_ID());
    }

    wp_reset_postdata();
}

if (!wp_next_scheduled('update_custom_items_cron')) {
    wp_schedule_event(time(), 'hourly', 'update_custom_items_cron');
}

add_action('update_custom_items_cron', 'update_custom_items_in_batch');
```

### 5. Compatibilità con Gutenberg e Temi Personalizzati

Il plugin è compatibile con **Gutenberg**, il nuovo editor di WordPress. Inoltre, offre supporto per temi personalizzati, consentendo di visualizzare facilmente i CTP tramite i template personalizzati.

### 6. Sicurezza

Le rotte REST sono protette da **autenticazione e autorizzazione** sicure, garantendo che solo gli utenti autorizzati possano modificare o visualizzare i dati. Tutti i parametri passati tramite le rotte sono **sanitizzati e validati** per evitare vulnerabilità come l'iniezione di SQL o altre minacce.

### 7. Performance e Ottimizzazione

Il plugin è progettato per gestire grandi quantità di dati senza compromettere le performance. Le operazioni massicce sono eseguite in **background** per ridurre il carico sul server. Inoltre, il plugin supporta **caching** per migliorare la velocità di caricamento.

## Contributi

Se desideri contribuire al progetto, invia una **pull request** o apri un **issue** nel repository GitHub.

## Supporto

Per qualsiasi domanda o problema, apri un **issue** su GitHub o contatta il supporto tramite il nostro canale di comunicazione.

---

### Tecnologie utilizzate

* **WordPress REST API**
* **Advanced Custom Fields (ACF)**
* **PHP**
* **JavaScript**
* **AJAX**
* **WP Cron**




