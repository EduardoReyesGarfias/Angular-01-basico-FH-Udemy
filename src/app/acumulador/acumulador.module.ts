import { NgModule } from '@angular/core';
import { AcumuladorComponente } from './acumulador/acumulador.component';

@NgModule({
    declarations:[
        AcumuladorComponente
    ],
    exports: [
        AcumuladorComponente
    ],
    imports: []
})

export class AcumuladorModule{

}