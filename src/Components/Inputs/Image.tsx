import React from 'react'
import ImageUploading, { ImageType } from 'react-images-uploading';
import * as uuid from 'uuid';
import { Input, InputProps, InputState } from '../Input'

interface ImageInputState extends InputState {
  images: Array<any>
  showImageLarge: boolean,
}

export default class Image extends Input<InputProps, ImageInputState> {
  static defaultProps = {
    inputClassName: 'image',
    id: uuid.v4(),
  }

  constructor(props: InputProps) {
    super(props);

    this.state = {
      ...this.state, // Parent state
      images: [],
      showImageLarge: false,
      isInitialized: true,
    };
  }

  onImageChange(images: Array<ImageType>, addUpdateIndex: any) {
    let image: any = images[0];

    this.onChange({
      fileName: image ? image.file.name : null,
      fileData: image ? image.fileData : null,
      // fileSize: image.file.size,
      // fileType: image.file.type
    });

    this.setState({
      images: images
    })

  };

  getImageUrl(): string {
    if (this.state.value) {
      if (this.state.value.fileData) {
        return this.state.value.fileData;
      } else if (this.state.value) {
        return globalThis.app.config.uploadUrl + '/' + this.state.value;
      } else {
        return '';
      }
    } else {
      return '';
    }
  }

  renderImage() {
    let btnStyle = (this.state.showImageLarge
      ? {width: '100%', height: 'calc(100vh - 2em)', padding: '1em', margin: 'auto', position: 'fixed', top: '0px', left: '0px', zIndex: 9999999, background: '#FFFFFF80'}
      : {height: '3em', paddingRight: '1em'}
    );

    let imgStyle = (this.state.showImageLarge ? {height: '100%', margin: 'auto'} : {height: '100%'});

    if (this.getImageUrl()) {
      return <>
        <button style={btnStyle} onClick={() => {this.setState({showImageLarge: !this.state.showImageLarge}) }}>
          <img src={this.getImageUrl()} style={imgStyle} />
        </button>
      </>;
    } else {
      return <></>;
    }
  }

  renderValueElement() {
    return this.renderImage();
  }

  renderInputElement() {
    return <>
      {this.renderImage()}
      <ImageUploading
        ref={this.refInput}
        value={this.state.value && this.state.value['fileData'] != null
          ? [this.state.value]
          : []
        }
        onChange={(images: Array<ImageType>, addUpdateIndex: any) => this.onImageChange(images, addUpdateIndex)}
        maxNumber={1}
        dataURLKey="fileData"
      >
        {({
          imageList,
          onImageUpload,
          onImageUpdate,
          onImageRemove,
          isDragging,
          dragProps,
        }) => (
            <div className="upload__image-wrapper">
              {/* {this.state.value && this.state.value['fileData'] != null
                ? ''
                : ( */}
                  <button
                    className="btn btn-small btn-transparent"
                    style={isDragging ? { color: 'red' } : undefined}
                    onClick={onImageUpload}
                    {...dragProps}
                  >
                    <span className="icon"><i className="fas fa-image"></i></span>
                    <span className="text">{this.translate("Choose image", 'ADIOS\\Core\\Loader::Components\\Inputs\\Image')}</span>
                  </button>
                {/* )
              } */}

              {/* {imageList.map((image, index) => (
                <div key={index} className="image-item">
                  <img src={image['fileData']} alt="" width="100" />
                  <div className="image-item__btn-wrapper text-left">
                    <button 
                      className="btn btn-light btn-sm text-info"
                      onClick={() => onImageUpdate(index)}
                    ><i className="fas fa-exchange-alt"></i></button>
                    <button 
                      className="btn btn-light btn-sm text-danger"
                      onClick={() => onImageRemove(index)}
                    ><i className="fas fa-trash-alt"></i></button>
                  </div>
                </div>
              ))} */}
            </div>
          )}
      </ImageUploading>
    </>;
  }
}
