import React, { Component, FC, useState, forwardRef } from "react";
import axios from "axios";
import moment, { Moment } from "moment";
import { dateToString, numberToStringTime } from "./Helper";
import Notification from "./Notification";

import Form from './Form';
import Modal from './Modal';

import SwalButton from "./SwalButton";

interface CalendarProps {
  uid: string,
  loadDataController?: string,
  loadDataUrl?: string,
  cviciska: Array<any>,
  timy: Array<any>,
  idSportovisko: number,
  jeKoordinator: boolean,
  jeSpravca: boolean
}

interface CalendarState {
  rCnt: number,
  data?: Array<Array<Array<any>>>,
  idTim?: number,
  idCvicisko?: number,
  poradie?: number,
  calendarTitle: string,
  datumOd: Date,
  datumDo: Date,
  rok?: number,
  tyzden?: number,
  isReadonly: boolean,
  warning?: string,
  info?: string,
  rezervaciaDatum?: string,
  rezervaciaCasOd?: string,
  casUprava?: number,
  idZaznam?: null,
  xxx: Array<any>
}

const hoursRange = Array.from({ length: 17 }, (_, index) => index + 6);
const hodiny = ['Po', 'Ut', 'St', 'Št', 'Pi', 'So', 'Ne'];

const dni = ['Po', 'Ut', 'St', 'Št', 'Pi', 'So', 'Ne'];

export default class Calendar extends Component<CalendarProps> {
  state: CalendarState;
  url: string;

  title: JSX.Element;

  constructor(props: CalendarProps) {
    super(props);

    console.log(props);
    let now: Date = new Date();
    let lastMonday: Date = new Date(now.getTime() - (now.getDay() - 1) * 24 * 60 * 60 * 1000);
    let lastDayInWeek = new Date(lastMonday.getTime() + 6 * 24 * 60 * 60 * 1000);

    this.state = {
      rCnt: 0,
      idCvicisko: this.props.cviciska[0]?.id ?? 0,
      idTim: this.props.timy.length != 0 ? this.props.timy[Object.keys(this.props.timy)[0]].id : 0,
      poradie: 0,
      calendarTitle: 'def',
      datumOd: lastMonday,
      datumDo: lastDayInWeek,
      isReadonly: false,
      xxx:  [
        { id: 1, name: "shrek" },
        { id: 2, name: "fiona" }
      ]
    };

    // console.log(this.state);

    this.url = props.loadDataController ?? props.loadDataUrl ?? '';
  }

  componentDidMount() {
    this.loadData();
  }

  loadData() {
    //@ts-ignore
    axios.get(_APP_URL + '/' + this.url, {
      params: {
        __IS_AJAX__: '1',
        idTim: this.state.idTim,
        idCvicisko: this.state.idCvicisko,
        datumOd: this.state.datumOd,
        datumDo: this.state.datumDo
      }
    }).then(({data}: any) => {

      let isReadonly = false;
      let warning = '';
      let info = '';

      if (this.props.jeSpravca) {
        isReadonly = false;
        info = 'Rozpis môžete upravovať ako správca.';
      } else if (this.props.jeKoordinator) {
        isReadonly = false;
        info = 'Rozpis môžete upravovať ako koordinátor.';
      } else {
        if (
          data.tyzden < this.getWeekNumber(new Date())
          || data.rok < (new Date()).getFullYear()
        ) {
          isReadonly = true;
          warning = 'Nemôžete upravovať rozpis, pretože je zobrazený týždeň v minulosti.';
        } else if (this.state.idTim) {
          if (this.props.timy[this.state.idTim].poradie != data.poradie) {
            isReadonly = true;
            warning = 'Nemôžete upravovať rozpis, pretože nie ste v poradí. Aktuálne poradie pre tento týždeň je ' + data.poradie + '.';
          }
        }
      }

      this.setState({
        data: data.data,
        poradie: data.poradie,
        rok: data.rok,
        tyzden: data.tyzden,
        isReadonly: isReadonly,
        warning: warning,
        info: info,
        casUprava: data.cvicisko?.cas_uprava ?? 0
      }, () => {
        //@ts-ignore
        this.sortable(document.getElementById('adios-calendar-' + this.props.uid), function(item: any) {
          console.log(item);
        });
      })
    });
  }

  idTimOnChange(event: React.ChangeEvent<HTMLInputElement>) {
    this.setState({
      idTim: event.target.value
    }, () => {
      this.loadData();
    });
  }

  idCviciskoOnChange(event: React.ChangeEvent<HTMLInputElement>) {
    this.setState({
      idCvicisko: event.target.value
    }, () => {
      this.loadData();
    });
  }

  formatTimeNumber(number: number) {
    return `${number < 10 ? '0' : ''}${number}`;
  }

  pickDateTime(slot: Moment, id?: number) {
    if (!this.state.isReadonly) {
      this.setState({
        idZaznam: id,
        rezervaciaDatum: `${slot.format('YYYY-MM-DD')}`,
        rezervaciaCasOd: `${slot.format('HH:mm')}`
      });

      //@ts-ignore
      ADIOS.modalToggle(this.props.uid + '-trening-form-modal');
    } else {
      Notification.error(this.state.warning ?? '');
    }
  }

  sortable(section: any, onUpdate: any) {
    var dragEl, nextEl, newPos, dragGhost;

    let oldPos = [...section.children].map(item => {
      if (item.id) {
        item.draggable = true;
        let pos = document.getElementById(item.id)?.getBoundingClientRect();

        return pos;
      }
    });
  
    function _onDragOver(e:  any) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';

      $('.rezervacka').removeClass('drag-over');
      var target = e.target;

      if (
        target
        && target !== dragEl
        && target.nodeName == 'DIV'
        && target.classList.contains('rezervacka')
        && $(target).data('den') == $(dragEl).data('den')
      ) {
          $(target).addClass('drag-over');
          //  getBoundinClientRect contains location-info about the element (relative to the viewport)
          var targetPos = target.getBoundingClientRect();

          // checking that dragEl is dragged over half the target y-axis or x-axis. (therefor the .5)
          var next =
            (e.clientY - targetPos.top) / (targetPos.bottom - targetPos.top) > .5
            || (e.clientX - targetPos.left) / (targetPos.right - targetPos.left) > .5
          ;
          var next =
            (e.clientX - targetPos.left) / (targetPos.right - targetPos.left) > .5
          ;

          //{# section.insertBefore(dragEl, next && target.nextSibling || target); #}
          section.insertBefore(dragEl, target.nextSibling);
      } else {
        e.stopPropagation();
      }
    }
    
    function _onDragEnd(evt: any){
      evt.preventDefault();
      newPos = [...section.children].map(child => {
        if (child.id) {
          let pos = document.getElementById(child.id)?.getBoundingClientRect();

          return pos;
        }
      });
      console.log(newPos);
      dragEl.classList.remove('ghost');
      section.removeEventListener('dragover', _onDragOver, false);
      section.removeEventListener('dragend', _onDragEnd, false);

      nextEl !== dragEl.nextSibling ? onUpdate(dragEl) : false;
    }
      
    section.addEventListener('dragstart', function(e: any){
      dragEl = e.target; 
      nextEl = dragEl.nextSibling;

      let dragGhostInner = dragEl.cloneNode(true);

      //{# dragGhost = document.createElement('div');
      //dragGhost.classList.add('hidden-drag-ghost');
      //dragGhost.appendChild(dragGhostInner);
      //document.body.appendChild(dragGhost);
      //e.dataTransfer.setDragImage(dragGhost, 0, 0); #}

      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('Text', dragEl.textContent);

      section.addEventListener('dragover', _onDragOver, false);
      section.addEventListener('dragend', _onDragEnd, false);

      setTimeout(function (){
        dragEl.classList.add('ghost');
      }, 0)

    });
  }


  getWeekNumber(date: Date) {
    let newDate = new Date(date);
    newDate.setDate(newDate.getDate() + 4 - (newDate.getDay() || 7));

    let year = newDate.getFullYear();
    let daysDiff = Math.floor((newDate.getTime() - new Date(year, 0, 4).getTime()) / (24 * 60 * 60 * 1000));

    return Math.ceil((daysDiff + 1) / 7);
  }

  calculateWeeks(type: string = 'next') {
    let weeks: Object = {};

    let datumOd = new Date(this.state.datumOd.getTime() + (type == 'next' ? 7 : -7) * 24 * 60 * 60 * 1000);

    weeks = {
      datumOd: datumOd,
      datumDo: new Date(this.state.datumDo.getTime() + (type == 'next' ? 7 : -7) * 24 * 60 * 60 * 1000),
    }

    this.setState({...weeks}, () => {
        this.loadData();
    })
  }

  closeAndLoadData(modalUid: string) {
    this.loadData();
    //@ts-ignore
    ADIOS.modalToggle(this.props.uid + '-' + modalUid);
    //@ts-ignore
    ADIOS.modalToggle(this.props.uid);
  }

  _addOpacity(color: string, opacity: string): string {
    return color + opacity;
  }

  _renderCalendar(): JSX.Element {
    if (!this.state.data) return <p>Loading</p>;

    return (
      <>
        {Array.from({ length: 7 }, (_, d) => (
          <>
            {(() => {
              let slot = moment(this.state.datumOd).add(d, 'days').startOf('day').set({hour: 6});

              if (!this.state.data) return;

              let dayLine =
                <>
                  <div className="header">{slot.format('D.M.YYYY')}</div>
                  <div className="header">{dni[d]}</div>

                  {this.state.data[d] ? (this.state.data[d].map((r: any) => (
                      <>{(r[1] === 0 && r[2] === '' && r[3] === '') ? (
                        Array.from({ length: r[0] / 15 }, (_, v) => {
                          const _slot = moment(slot);

                          let div = (
                            <div
                              id={`rezervacka-${this.state.rCnt++}`}
                              key={this.state.rCnt}
                              className={
                                "rezervacka volne "
                                + (this.state.rCnt % 4 == 0 ? "cela-hodina" : "")
                                + " " + (r[7] == this.state.idTim ? "zvyraznene" : "")
                              }
                              title={slot.format('D.M.YYYY HH:mm')}
                              data-den={d}
                              onClick={() => this.pickDateTime(_slot)}
                            ></div>
                          );

                          slot = slot.add(15, 'minutes');

                          return div
                        })
                      ) : (
                        <>
                          {(() => {
                            const _slot = moment(slot);
                            const span = (r[0] + r[1]) / 15;
                            const gridColumn = `span ${span}`;

                            this.state.rCnt += span;

                            // let hh = slot.hours();
                            // let mm = slot.minutes();

                            let div = (
                              <div
                                id={`rezervacka-${this.state.rCnt}`}
                                key={this.state.rCnt}
                                className={
                                  "rezervacka"
                                  + " " + (r[7] == this.state.idTim ? "zvyraznene" : "")
                                }
                                title={slot.format('D.M.YYYY HH:mm')}
                                data-den={d}
                                style={{ gridColumn }}
                              >
                                <div className="rezervacka-inner" style={{ flex: r[0] / 15 }}>
                                  {Array.isArray(r[2]) ? (
                                    r[2].map((rr) => (
                                      <div
                                        key={rr[1]}
                                        className="cast-cviciska"
                                        style={{ background: this._addOpacity(rr[0], '60') }}
                                        onClick={() => {
                                        }}
                                      >
                                        {rr[1]}
                                      </div>
                                    ))
                                  ) : (
                                    <div
                                      className="cast-cviciska"
                                      style={{ background: this._addOpacity(r[2], '60') }}
                                      onClick={() => {
                                        if (r[7] == this.state.idTim) {
                                            this.pickDateTime(_slot, r[6]);
                                        }
                                      }}
                                    >
                                      <div className="cas">{numberToStringTime(_slot.hours()) + ':' + numberToStringTime(_slot.minutes())}</div>
                                      <div className="nazov">{r[3]}</div>
                                      <div className="trener">{r[4]}</div>
                                    </div>
                                  )}
                                </div>
                                <div className="rolba" style={{ flex: r[1] / 15 }}></div>
                              </div>
                            );

                            slot = slot.add(span*15, 'minutes');

                            return div;
                          })()}
                        </>
                      )}
                  </>))) : ''}
                </>
              ;

              return dayLine;
            })()}
          </>
        ))}
      </>
    );
  }

  render() {
    return (
      <>
        {/*<Modal
          title={
            "Rezervácia cvičiska "
            + (this.state.rezervaciaDatum ? this.state.rezervaciaDatum : '')
            + ' '
            + (this.state.rezervaciaCasOd ? this.state.rezervaciaCasOd : '')
          }
          uid={this.props.uid + '-zvolit-typ-rezervacie'}
        >
          <FormCardButton
            uid={this.props.uid + '-trening'}
            text="Tréning"
            css="btn-primary m-1"
            icon="fas fa-running"
            form={{
              uid: this.props.uid + '-trening',
              model: "App/Widgets/Rozpis/Models/Trening",
              onSaveCallback: () => this.closeAndLoadData('trening'),
              onDeleteCallback: () => this.closeAndLoadData('trening'),
              defaultValues: {
                datum: this.state.rezervaciaDatum,
                zaciatok: this.state.rezervaciaCasOd,
                id_tim: this.state.idTim,
                id_cvicisko: this.state.idCvicisko,
                vyuzitie: 1, //cela plocha default,
                cas_uprava: this.state.casUprava
              }
            }}
          ></FormCardButton>

          <FormCardButton
            uid={this.props.uid + '-zapas'}
            text="Zápas"
            css="btn-light"
            icon="fas fa-people-arrows m-1"
            form={{
              uid: this.props.uid + '-zapas',
              model: "App/Widgets/Rozpis/Models/Zapas",
              onSaveCallback: () => this.closeAndLoadData('zapas'),
              onDeleteCallback: () => this.closeAndLoadData('zapas'),
              defaultValues: {
                datum: this.state.rezervaciaDatum,
                zaciatok: this.state.rezervaciaCasOd
              }
            }}
          ></FormCardButton>

          <FormCardButton
            uid={this.props.uid + '-prenajom'}
            text="Prenájom"
            css="btn-light m-1"
            icon="fas fa-euro-sign"
            form={{
              uid: this.props.uid + '-prenajom',
              model: "App/Widgets/Rozpis/Models/Prenajom",
              onSaveCallback: () => this.closeAndLoadData('prenajom'),
              onDeleteCallback: () => this.closeAndLoadData('prenajom'),
              defaultValues: {
                datum: this.state.rezervaciaDatum,
                zaciatok: this.state.rezervaciaCasOd
              }
            }}
          ></FormCardButton>
        </Modal>*/}

        <Modal
          uid={this.props.uid + '-trening-form-modal'}
          hideHeader={true}
        >
          <Form
            uid={this.props.uid + '-trening-form'}
            showInModal={true}
            model="App/Widgets/Rozpis/Models/Trening"
            onSaveCallback={() => this.closeAndLoadData('trening-form-modal')}
            onDeleteCallback={() => this.closeAndLoadData('trening-form-modal')}
            id={this.state.idZaznam ?? undefined}
            defaultValues={{
              datum: this.state.rezervaciaDatum,
              zaciatok: this.state.rezervaciaCasOd,
              id_tim: this.state.idTim,
              id_cvicisko: this.state.idCvicisko,
              id_sportovisko: this.props.idSportovisko,
              vyuzitie: 1, //cela plocha default
              trvanie_uprava: this.state.casUprava
            }}
          ></Form>
        </Modal>

        <div className="row mt-1 mb-2">
          <div className="col-lg-6 pl-0">
            <div className="d-flex flex-row align-items-center">
              <select
                className="form-control rounded-sm"
                style={{maxWidth: '250px'}}
                onChange={(event: any) => this.idCviciskoOnChange(event)}
                value={this.state.idCvicisko}
              >
                {this.props.cviciska.map((cvicisko: any) => (
                  <option
                    key={cvicisko.id}
                    value={cvicisko.id}
                  >{cvicisko.nazov}</option>
                ))}
              </select>

              <span className="ml-2 mr-2">&raquo;</span>

              <select
                className="form-control rounded-sm"
                onChange={(event: any) => this.idTimOnChange(event)}
                value={this.state.idTim}
              >
                {Object.keys(this.props.timy).map((key: any) => (
                  <option
                    key={this.props.timy[key].id}
                    value={this.props.timy[key].id}
                  >[{this.props.timy[key].poradie}] {this.props.timy[key].nazov}</option>
                ))}
              </select>
            </div>
          </div>
          <div className="col-lg-6 pr-0">
            <div className="d-flex flex-row justify-content-end align-items-center">
              <a href="#" onClick={() => this.calculateWeeks('previous')} className="btn btn-primary">«</a>
              <div className="text-primary text-center" style={{margin: "0 0.5em", width: "200px"}}>
                { this.state.datumOd.getDate() }.{ this.state.datumOd.getMonth() + 1 }.{ this.state.datumOd.getFullYear() }
                &nbsp;-&nbsp;
                { this.state.datumDo.getDate() }.{ this.state.datumDo.getMonth() + 1 }.{ this.state.datumOd.getFullYear() }
              </div>
              <a href="#" onClick={() => this.calculateWeeks()} className="btn btn-primary">»</a>
            </div>
          </div>
        </div>

        <div id={'adios-calendar-' + this.props.uid} className="rezervacny-kalendar rounded-sm">
          <div className="header" style={{ gridRow: 'span 2' }}>Dátum</div>
          <div className="header">Hodina</div>

          {hoursRange.map((h) => (
            <div key={h} className="header hodiny">
              {h} - {h + 1}
            </div>
          ))}

          <div className="header">Deň</div>
          {this._renderCalendar()}
        </div>

        <div className="row mt-4">
          <div className="col-lg-8">
            {this.state.isReadonly ? (
              <div className='alert alert-danger' role='alert'>
                <i className='fas fa-exclamation-triangle mr-4 align-self-center'></i>
                {this.state.warning}
              </div>
            ) : (
                <div className='alert alert-success' role='alert'>
                  <i className='fas fa-check mr-4 align-self-center'></i>
                  {this.state.info}<br/>
                </div>
              )
            }

            <div className='alert alert-info' role='alert'>
              <i className='fas fa-info mr-4 align-self-center'></i>
              Aktuálne poradie pre zvolené cvičisko a týždeň je [{this.state.poradie}].
            </div>

          </div>
          <div className="col-lg-4 text-right">
            {!this.state.isReadonly ? (
              <>
                <SwalButton
                  uid={this.props.uid}
                  text="Ukončiť tvorbu rozpisu"
                  css="btn-success"
                  icon="fas fa-check"
                  confirmUrl="rozpis/kalendar/potvrdit-rozpis"
                  successMessage="Rozpis úspešne ukončený"
                  confirmParams={{
                    idSportovisko: this.props.idSportovisko,
                    idCvicisko: this.state.idCvicisko,
                    rok: this.state.rok,
                    tyzden: this.state.tyzden
                  }}
                  onConfirmCallback={() => this.loadData()}
                  swal={{
                    title: "Ukončiť tvorbu rozpisu",
                    html:`
                      <p>
                        Chystáte sa ukončiť tvorbu rozpisu v týždni <b>${dateToString(this.state.datumOd)} - ${dateToString(this.state.datumDo)}</b>
                        na cvičisku ${this.state.idCvicisko}.<br/>
                      </p>

                      <div class='alert alert-danger' role='alert'>
                        <i class='fas fa-exclamation-triangle mr-4 align-self-center'></i>
                        Krok sa nedá vrátiť späť. Ďalšie zmeny v rozpise bude môcť vykonať iba klubový koordinátor.<br/>
                      </div>

                      <div class='alert alert-success' role='alert'>
                        <i class='fas fa-check mr-4 align-self-center'></i>
                        Potvrdením umožníte tvoriť rozpis ďalšiemu trénerovi v poradí.
                      </div>
                    `,
                    icon: "info",
                    confirmButtonText: "Potvrdiť",
                    confirmButtonColor: '#1cc88a',
                    cancelButtonText: "Zrušiť"
                  }}
                ></SwalButton>

                <div className="mt-2">
                  Umožníte tvoriť rozpis ďalšiemu trénerovi v poradí.
                </div>
              </>
            ) : ''}
          </div>
        </div>
      </>
    );
  }
}

