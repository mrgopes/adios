import React, { Component } from 'react'
import * as uuid from 'uuid';
import Form from './Form';

export interface InputDescription {
  type?: string,
  title?: string,
  readonly?: boolean,
  required?: boolean,
  placeholder?: string,
  decimals?: number,
  unit?: string,
  format?: string,
  description?: string,
  reactComponent?: string,
  lookupModel?: string,
  enumValues?: Array<any>,
  enumCssClasses?: Array<any>,
  autocomplete?: { endpoint: string, creatable: boolean },
  predefinedValues?: any,
  endpoint?: any,
  model?: any,
  info?: any,
}

export interface InputProps {
  uid: string,
  inputName?: string,
  inputClassName?: string,
  value?: any,
  onChange?: (input: any, value: any) => void,
  readonly?: boolean,
  invalid?: boolean,
  cssClass?: string,
  placeholder?: string,
  isInitialized?: boolean,
  isInlineEditing?: boolean,
  showInlineEditingButtons?: boolean,
  onInlineEditCancel?: () => void,
  onInlineEditSave?: () => void,
  context?: any,
  parentForm?: Form<any, any>,
  children?: any,
  description?: InputDescription,
}

export interface InputState {
  readonly: boolean,
  invalid: boolean,
  value: any,
  origValue: any,
  onChange: (value: any) => void,
  cssClass: string,
  isInitialized: boolean,
  isInlineEditing: boolean,
  showInlineEditingButtons: boolean,
  description: InputDescription,
}

export class Input<P extends InputProps, S extends InputState> extends Component<P, S> {
  static defaultProps = {
    inputClassName: '',
    id: uuid.v4(),
  };

  state: S;
  translationContext: string = 'input';

  refInputWrapper: any;
  refInputElement: any;
  refValueElement: any;
  refInput: any;

  constructor(props: P) {
    super(props);

    this.refInputWrapper = React.createRef();
    this.refInputElement = React.createRef();
    this.refValueElement = React.createRef();
    this.refInput = React.createRef();

    globalThis.app.reactElements[this.props.uid] = this;

    const isInitialized: boolean = props.isInitialized ?? false;
    const isInlineEditing: boolean = props.isInlineEditing ?? true;
    const showInlineEditingButtons: boolean = props.showInlineEditingButtons ?? false;
    const readonly: boolean = props.readonly ?? false;
    const invalid: boolean = props.invalid ?? false;
    const value: any = props.value;
    const onChange: any = props.onChange ?? null;
    const cssClass: string = props.cssClass ?? '';
    const description: any = props.description ?? null;

    this.state = {
      isInitialized: isInitialized,
      isInlineEditing: isInlineEditing,
      showInlineEditingButtons: showInlineEditingButtons,
      readonly: readonly,
      invalid: invalid,
      value: value,
      origValue: value,
      onChange: onChange,
      cssClass: cssClass,
      description: description,
    } as S;
  }

  componentDidMount() {
    if (this.props.parentForm && this.props.inputName) {
      this.props.parentForm.inputs[this.props.inputName.toString()] = this;
    }
  }

  componentDidUpdate(prevProps: any): void {
    let newState: any = {};
    let setNewState: boolean = false;

    if (this.props.isInitialized != prevProps.isInitialized) {
      newState.isInitialized = this.props.isInitialized;
      setNewState = true;
    }

    if (this.props.isInlineEditing != prevProps.isInlineEditing) {
      newState.isInlineEditing = this.props.isInlineEditing;
      setNewState = true;
    }

    if (this.props.showInlineEditingButtons != prevProps.showInlineEditingButtons) {
      newState.showInlineEditingButtons = this.props.showInlineEditingButtons;
      setNewState = true;
    }

    if (this.props.value != prevProps.value) {
      newState.value = this.props.value;
      setNewState = true;
    }

    if (this.props.cssClass != prevProps.cssClass) {
      newState.cssClass = this.props.cssClass;
      setNewState = true;
    }

    if (this.props.readonly != prevProps.readonly) {
      newState.readonly = this.props.readonly;
      setNewState = true;
    }

    if (this.props.invalid != prevProps.invalid) {
      newState.invalid = this.props.invalid;
      setNewState = true;
    }

    if (this.props.description != prevProps.description) {
      newState.description = this.props.description;
      setNewState = true;
    }

    if (setNewState) {
      this.setState(newState);
    }
  }

  translate(orig: string, context?: string): string {
    return globalThis.app.translate(orig, context ?? this.translationContext);
  }

  getClassName() {
    return (
      "adios component input"
      + " " + this.props.inputClassName
      + " " + (this.state.invalid ? 'invalid' : '')
      + " " + (this.state.cssClass ?? "")
      + " " + (this.state.readonly ? "bg-muted" : "")
    );
  }

  onChange(value: any) {
    this.setState({value: value}, () => {
      if (typeof this.props.onChange == 'function') {
        this.props.onChange(this, value);
      }
    });
  }

  serialize(): string {
    return this.state.value ? this.state.value.toString() : '';
  }

  inlineEditEnable() {
    if (!this.state.readonly) {
      this.setState({
        origValue: this.state.value,
        isInlineEditing: true,
      }, () => {
        if (this.props.parentForm) {
          this.props.parentForm.setState({isInlineEditing: true});
        }
      });
    }
  }

  inlineEditSave() {
    this.setState(
      {
        origValue: this.state.value,
        isInlineEditing: false
      },
      () => {
        if (this.props.onInlineEditSave) {
          this.props.onInlineEditSave()
        }
      }
    );
  }

  inlineEditCancel() {
    this.setState(
      {
        value: this.state.origValue,
        isInlineEditing: false,
      },
      () => {
        this.onChange(this.state.origValue);
        if (this.props.onInlineEditCancel) {
          this.props.onInlineEditCancel()
        }
      }
    );
  }

  renderLoadingInfo() {
    return <div className="badge badge-warning">[loading]</div>;
  }

  renderInputElement() {
    return <input
      type="text"
      value={this.state.value ?? ''}
      readOnly={this.state.readonly}
      ref={this.refInput}
    ></input>;
  }

  renderValueElement() {
    let value = (this.state.value ?? '') + '';
    if (value == '') return <span className="no-value"></span>;
    else return <span>{this.state.value.toString()}</span>;
  }

  render() {
    if (!this.state.isInitialized) return this.renderLoadingInfo();

    try {
      globalThis.app.setTranslationContext(this.translationContext);

      return (
        <div ref={this.refInputWrapper} className={this.getClassName() + (this.state.isInlineEditing ? ' editing' : '')}><div className="inner">
          {this.state.isInlineEditing
            ? <>
              <input
                id={this.props.uid}
                name={this.props.uid}
                type="hidden"
                value={this.serialize()}
                style={{width: "100%", fontSize: "0.4em"}}
                className="value bg-light"
                readOnly={true}
              ></input>
              <div ref={this.refInputElement} className="input-element">
                {this.renderInputElement()}
                {this.props.description?.unit ? <div className="input-unit">{this.props.description.unit}</div> : null}
              </div>
              {this.state.showInlineEditingButtons ? 
                <div className="inline-editing-buttons always-visible">
                  <button
                    className={"btn btn-success-outline"}
                    onClick={() => {
                      this.inlineEditSave();
                    }}
                  >
                    <span className="icon !py-0"><i className="fas fa-check"></i></span>
                  </button>
                  <button
                    className={"btn btn-cancel-outline"}
                    onClick={() => {
                      this.inlineEditCancel();
                    }}
                  >
                    <span className="icon !py-0"><i className="fas fa-times"></i></span>
                  </button>
                </div>
                : null
              }
            </>
            : <>
              <div ref={this.refValueElement} className="value-element" onClick={() => { this.inlineEditEnable(); }}>
                {this.renderValueElement()}
                {this.props.description?.unit ? <div className="input-unit">{this.props.description.unit}</div> : null}
              </div>
              {/* {this.state.readonly ? null :
                <div className="inline-editing-buttons">
                  <button
                    className="btn btn-transparent"
                    onClick={() => {
                      this.inlineEditEnable();
                    }}
                  >
                    <span className="icon !py-0"><i className="fas fa-pencil-alt"></i></span>
                  </button>
                </div>
              } */}
            </>
          }
        </div></div>
      );
    } catch(e) {
      const errMsg = 'Failed to render input for ' + (this.props.description?.title ?? this.props.inputName) + '.';
      console.error(errMsg);
      console.error(e);
      return <div className="alert alert-danger">{errMsg} Check console for error log.</div>
    }
  }
}
