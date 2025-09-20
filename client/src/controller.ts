import { Component, OnDestroy, OnInit } from '@angular/core';
import { StripeService } from '../../../../services/stripe/stripe.service';
import { Router } from '@angular/router';
import { UserDataService } from '../../../../services/user-management/user-data.service';
import { DataService } from '../../../../services/data/data.service';

// const MAX_POLLING = 10;
// const EXIT_NUMBER = 999;

export enum DocusignStatus{
  NOT_SENT = 'not_sent',
  SIGNED = 'signed',
  SENT = 'SENT',
  POLLING = 'polling'
}

enum DocusignEvent{
  SIGNING_COMPLETE = "signing_complete",
  VIEWING_COMPLETE = "viewing_complete",
  DECLINED = "declined",
  CANCELED = "cancelled"
}

interface DocusignCheck{
  success: boolean,
  verify: boolean,
  id_envelope: string | null,
  date: string | null,
  messagge: string
}

@Component({
  selector: 'app-docusign',
  templateUrl: './docusign.component.html',
  styleUrl: './docusign.component.css'
})
export class DocusignComponent implements OnInit, OnDestroy{

  DocusignStatus = DocusignStatus;

  utente: any | null = null;
  status: DocusignStatus | null = null;
  tier: string | null = null;
  idEnvelope: string | null = null;
  signatureLink: string | null = null;

  isLoading = false;
  pollingCounter = 0;

  constructor(
    private stripeService: StripeService,
    private dataService: DataService,
    private userDataService: UserDataService,
  ){}

  ngOnInit(): void {
    const tierValue = this.getTier();

    this.userDataService.utenteAttuale.subscribe((utenteValue: any) => {

      if(!utenteValue){
        alert("Errore nel recupero dei dati");
        return;
      }

      const URLEvent = new URLSearchParams(window.location.search);
      const event = URLEvent.get('event');

      console.log("evento = " +event);

      if(event == DocusignEvent.SIGNING_COMPLETE){
        this.status = DocusignStatus.SIGNED;
        localStorage.setItem("status-"+this.tier,"signed");
      }
      else if(event == DocusignEvent.VIEWING_COMPLETE || event == DocusignEvent.CANCELED){
        this.status = DocusignStatus.SENT;
        localStorage.setItem("status-"+this.tier,"sent");
      }
      else if(event == null){
        this.dataService.checkDocusignSignature(utenteValue.id, tierValue).subscribe((docusignCheck: DocusignCheck) => {

          const secondsSinceSent = this.getSecondsUntilDate(docusignCheck.date)

          const statusValue = localStorage.getItem("status-"+tierValue);

          console.log("stato = " +statusValue);

          if(docusignCheck.verify || statusValue == 'signed'){
            this.status = DocusignStatus.SIGNED;
          }
          // else if(statusValue == 'sent'){
          //   this.status = DocusignStatus.SENT;
          // }
          // else if(!docusignCheck.verify && docusignCheck.id_envelope && docusignCheck.date && secondsSinceSent < 45){
          //   this.status = DocusignStatus.POLLING;
          //   this.startPolling();
          // }
          // else if(!docusignCheck.verify && docusignCheck.id_envelope && docusignCheck.date && secondsSinceSent >= 45){
          //   this.status = DocusignStatus.SENT;
          // }
          else if((!docusignCheck.verify && docusignCheck.id_envelope) || statusValue == "sent"){
            this.status = DocusignStatus.SENT;
          }
          else{
            this.status = DocusignStatus.NOT_SENT;
          }
          this.idEnvelope = docusignCheck.id_envelope;
        });
      }
      this.utente = utenteValue;
    });
    
    this.tier = tierValue;
  }

  ngOnDestroy() {
    localStorage.removeItem('selected-tier');
  }

  getTier(){
    const tierValue = localStorage.getItem("selected-tier");
    if (!tierValue) {
      throw new Error("Errore nel recupero dei dati");
    }
    return tierValue; 
  }

  getSecondsUntilDate(strDate: string | null){
    if(!strDate)
      return EXIT_NUMBER;
    const formattedDate = strDate.replace(' ', 'T') + 'Z';
    const dateSent = new Date(formattedDate);
    const now = new Date();
    return (now.getTime() - dateSent.getTime()) / 1000;
  }

  startSubscriptionProcess(){
    if(!this.tier){
      alert("Errore nel recupero dei dati");
      return;
    }
    this.stripeService.getSubscriptionCheckoutSessionUrl(this.tier).subscribe((stripe: any) =>{
      window.location.href = stripe.toString();
    });
  }

  regenerateSignatureLink(){
    if(!this.utente){
      alert("Errore nel recupero dei dati dell'utente");
      return;
    }

    if(!this.idEnvelope){
      alert("Errore nel recupero del envelop id");
      return;
    }

    if(this.status != DocusignStatus.SENT){
      alert("Errore nell stato della richiesta");
      return;
    }

    this.isLoading = true;

    this.dataService.regenerateEnvelope(this.utente.email, this.utente.username, this.idEnvelope).subscribe({
      next: (response: any) => {
        this.signatureLink = response.url.toString();
        console.log('Link Docusign generato con successo:', this.signatureLink);
      },
      error: (err) => {
        alert('Errore durante la generazione del link Docusign:');
        console.error('Errore durante la generazione del link Docusign:', err);
      },
      complete: () => {
        this.isLoading = false;
        localStorage.setItem("status-"+this.tier,"sent");
      }
    });
  }

  generateSignatureLink(){
    if(!this.utente){
      alert("Errore nel recupero dei dati dell'utente");
      return;
    }

    if(this.status != DocusignStatus.NOT_SENT){
      alert("Errore nell stato della richiesta");
      return;
    }

    if(!this.tier){
      alert("Errore nell stato della richiesta");
      return;
    }

    this.isLoading = true;

    this.dataService.loadEnvelope(this.utente.email, this.utente.username, this.tier, this.utente.id).subscribe({
      next: (response: any) => {
        this.signatureLink = response.url.toString();
        console.log('Link Docusign generato con successo:', this.signatureLink);
      },
      error: (err) => {
        alert('Errore durante la generazione del link Docusign:');
        console.error('Errore durante la generazione del link Docusign:', err);
      },
      complete: () => {
        this.isLoading = false;
        localStorage.setItem("status-"+this.tier,"sent");
      }
    }); 
  }

  // startPolling(){
  //   if(this.pollingCounter >= MAX_POLLING){
  //     this.status = DocusignStatus.SENT;
  //     return;
  //   }

  //   if(!this.tier){
  //     alert("Errore nel recupero dei dati");
  //     return;
  //   }
      
  //   this.dataService.checkDocusignSignature(this.utente.id, this.tier).subscribe((docusignCheck: DocusignCheck) => {
  //     if(docusignCheck.verify){
  //       this.status = DocusignStatus.SIGNED;
  //       this.pollingCounter = 0;
  //     }else{
  //       this.pollingCounter++;
  //       setTimeout(() => {
  //         this.startPolling();
  //     }, 3000);
  //     }
  //   });
  // }

}
